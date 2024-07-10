<?php

namespace Crm\SalesFunnelModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\Events\SalesFunnelCreatedEvent;
use Crm\SalesFunnelModule\Events\SalesFunnelUpdatedEvent;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Database\UniqueConstraintViolationException;

class SalesFunnelsRepository extends Repository
{
    protected $tableName = 'sales_funnels';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        private Emitter $emitter,
        private SalesFunnelsSubscriptionTypesRepository $salesFunnelsSubscriptionTypesRepository,
        private SalesFunnelsPaymentGatewaysRepository $salesFunnelsPaymentGatewaysRepository,
        private SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        private SalesFunnelTagsRepository $salesFunnelTagsRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
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
        $redirectFunnelId = null,
        $limitPerUser = null,
        $note = null
    ) {
        try {
            $result = $this->insert([
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
                'limit_per_user' => $limitPerUser,
                'note' => $note,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new SalesFunnelAlreadyExistsException('Sales funnel already exists: '. $urlKey);
        }

        $this->emitter->emit(new SalesFunnelCreatedEvent($result));
        return $result;
    }

    final public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function active(): Selection
    {
        return $this->all()->where(['is_active' => true]);
    }

    final public function findByActive(bool $active): Selection
    {
        return $this->all()->where(['is_active' => $active]);
    }

    final public function findByUrlKey($urlKey)
    {
        return $this->getTable()->where('url_key', $urlKey)->fetch();
    }

    final public function update(ActiveRow &$row, $data)
    {
        $oldSalesFunnel = $this->find($row->id);

        $data['updated_at'] = new DateTime();
        $result = parent::update($row, $data);

        $this->emitter->emit(new SalesFunnelUpdatedEvent($oldSalesFunnel, $row));
        return $result;
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
        return $this->increment($funnel, 'total_conversions');
    }

    final public function incrementErrors(ActiveRow $funnel)
    {
        return $this->increment($funnel, 'total_errors');
    }

    private function increment(ActiveRow $funnel, string $field, $value = 1)
    {
        $formattedDate = (new DateTime())->format('Y-m-d H:i:s');

        $query = "UPDATE sales_funnels SET {$field}={$field}+{$value}, last_use='{$formattedDate}'";

        if ($field === 'total_conversions') {
            $query .= ", last_conversion='{$formattedDate}'";
        }

        $query .= " WHERE id={$funnel->id}";

        return $this->getDatabase()->query($query);
    }

    final public function totalPaidAmount(ActiveRow $funnel)
    {
        return $this->getTable()
            ->where(':payments.status = ?', PaymentsRepository::STATUS_PAID)
            ->where(':payments.sales_funnel_id = ?', $funnel->id)
            ->sum(':payments.amount') ?? 0;
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

    final public function duplicate(ActiveRow $funnel, string $name, string $urlKey): ActiveRow
    {
        $newFunnel = $this->add(
            $name,
            $urlKey,
            $funnel->body,
            $funnel->head_meta,
            $funnel->head_script,
            $funnel->start_at,
            $funnel->end_at,
            $funnel->is_active,
            $funnel->only_logged,
            $funnel->only_not_logged,
            $funnel->segment,
            $funnel->no_access_html,
            $funnel->error_html,
            $funnel->redirect_funnel_id,
            $funnel->limit_per_user
        );

        foreach ($funnel->related('sales_funnels_payment_gateways') as $paymentGateway) {
            $this->salesFunnelsPaymentGatewaysRepository->add($newFunnel, $paymentGateway->payment_gateway);
        }

        foreach ($funnel->related('sales_funnels_subscription_types') as $subscriptionType) {
            $this->salesFunnelsSubscriptionTypesRepository->add($newFunnel, $subscriptionType->subscription_type);
        }

        foreach ($funnel->related('sales_funnel_tags') as $funnelTag) {
            $this->salesFunnelTagsRepository->add($newFunnel, $funnelTag->tag);
        }

        foreach ($funnel->related('sales_funnels_meta') as $meta) {
            $this->salesFunnelsMetaRepository->add($newFunnel, $meta->key, $meta->value);
        }

        return $newFunnel;
    }
}
