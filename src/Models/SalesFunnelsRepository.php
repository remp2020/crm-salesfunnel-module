<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use DateTime;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class SalesFunnelsRepository extends Repository
{
    protected $tableName = 'sales_funnels';

    private $salesFunnelsSubscriptionTypesRepository;

    private $salesFunnelsPaymentGatewaysRepository;

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        SalesFunnelsSubscriptionTypesRepository $salesFunnelsSubscriptionTypesRepository,
        SalesFunnelsPaymentGatewaysRepository $salesFunnelsPaymentGatewaysRepository
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->salesFunnelsSubscriptionTypesRepository = $salesFunnelsSubscriptionTypesRepository;
        $this->salesFunnelsPaymentGatewaysRepository = $salesFunnelsPaymentGatewaysRepository;
    }

    public function add(
        $name,
        $urlKey,
        $body,
        $headMeta = null,
        $headScript = null,
        DateTime $startAt = null,
        DateTime $endAt = null,
        $isActive = true,
        $onlyLogged = false,
        $onlyNotLogged = false,
        ActiveRow $segment = null,
        $noAccessHtml = null,
        $errorHtml = null,
        $redirectFunnelId = null
    ) {
        return $this->insert([
            'name' => $name,
            'url_key' => $urlKey,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'body' => $body,
            'no_access_html' => $noAccessHtml,
            'error_html' => $errorHtml,
            'head_meta' => $headMeta,
            'head_script' => $headScript,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'is_active' => $isActive,
            'only_logged' => $onlyLogged,
            'only_not_logged' => $onlyNotLogged,
            'segment_id' => $segment ? $segment->id : null,
            'redirect_funnel_id' => $redirectFunnelId,
        ]);
    }

    final public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function active(): Selection
    {
        return $this->all()->where(['is_active' => true]);
    }

    final public function findByUrlKey($urlKey)
    {
        return $this->getTable()->where('url_key', $urlKey)->fetch();
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function incrementShows(ActiveRow $funnel)
    {
        return $this->increment($funnel, 'total_show');
    }

    final public function incrementLoggedInShows(ActiveRow $funnel)
    {
        return $this->increment($funnel, 'loggedin_show');
    }

    final public function incrementNotLoggedInShows(ActiveRow $funnel)
    {
        return $this->increment($funnel, 'notloggedin_show');
    }

    final public function incrementConversions(ActiveRow $funnel)
    {
        $this->update($funnel, ['last_conversion' => new DateTime()]);
        return $this->increment($funnel, 'total_conversions');
    }

    final public function incrementErrors(ActiveRow $funnel)
    {
        return $this->increment($funnel, 'total_errors');
    }

    private function increment(ActiveRow $funnel, $field, $value = 1)
    {
        $date = new DateTime();
        return $this->getDatabase()->query("UPDATE sales_funnels SET {$field}={$field}+{$value}, last_use='{$date->format('Y-m-d H:i:s')}' WHERE id=" . $funnel->id);
    }

    final public function totalPaidAmount(ActiveRow $funnel)
    {
        return $this->getTable()
            ->where(':payments.status = ?', PaymentsRepository::STATUS_PAID)
            ->where(':payments.sales_funnel_id = ?', $funnel->id)
            ->sum(':payments.amount');
    }

    final public function getSalesFunnelsBySubscriptionType(ActiveRow $subscriptionType)
    {
        return $this->getTable()->where([':sales_funnels_subscription_types.subscription_type_id' => $subscriptionType->id]);
    }

    final public function getAllUserSalesFunnelPurchases($userId, $salesFunnelId)
    {
        return $this->getTable()->where([
            'sales_funnels.id' => $salesFunnelId,
            ':payments.user_id' => $userId,
        ])->where(':payments.status = ?', PaymentsRepository::STATUS_PAID);
    }

    /**
     * @param ActiveRow $funnel
     * @return array
     */
    final public function getSalesFunnelSubscriptionTypes(ActiveRow $funnel)
    {
        $subscriptionTypes = [];
        foreach ($this->salesFunnelsSubscriptionTypesRepository->findAllBySalesFunnel($funnel) as $row) {
            $subscriptionTypes[] = $row->subscription_type;
        }

        return $subscriptionTypes;
    }

    final public function getSalesFunnelDistribution(ActiveRow $funnel)
    {
        return $this->getTable()
            ->select(':payments.subscription_type_id, COUNT(*) AS count')
            ->where([
                'sales_funnels.id' => $funnel->id,
                ':payments.status' => PaymentsRepository::STATUS_PAID,
            ])
            ->group(':payments.subscription_type_id')
            ->fetchPairs('subscription_type_id', 'count');
    }

    final public function getSalesFunnelGateways(ActiveRow $funnel)
    {
        $gateways = [];
        foreach ($this->salesFunnelsPaymentGatewaysRepository->findAllBySalesFunnel($funnel) as $row) {
            if ($row->payment_gateway->visible) {
                $gateways[] = $row->payment_gateway;
            }
        }
        return $gateways;
    }

    final public function getAllSalesFunnelPurchases($salesFunnelId)
    {
        return $this->getTable()->where([
            ':payments.sales_funnel_id' => $salesFunnelId
        ])->where(':payments.paid_at IS NOT NULL');
    }
}
