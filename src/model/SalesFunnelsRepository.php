<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Distribution\SubscriptionDaysDistribution;
use DateTime;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class SalesFunnelsRepository extends Repository
{
    protected $tableName = 'sales_funnels';

    private $subscriptionDaysDistribution;

    public function __construct(
        Context $database,
        SubscriptionDaysDistribution $subscriptionDaysDistribution,
        AuditLogRepository $auditLogRepository
    ) {
        parent::__construct($database);
        $this->subscriptionDaysDistribution = $subscriptionDaysDistribution;
        $this->auditLogRepository = $auditLogRepository;
    }

    public function add(
        $name,
        $urlKey,
        $body,
        $head = null,
        DateTime $startAt = null,
        DateTime $endAt = null,
        $isActive = true,
        $onlyLogged = false,
        $onlyNotLogged = false,
        IRow $segment = null,
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
            'head' => $head,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'is_active' => $isActive,
            'only_logged' => $onlyLogged,
            'only_not_logged' => $onlyNotLogged,
            'segment_id' => $segment ? $segment->id : null,
            'redirect_funnel_id' => $redirectFunnelId,
        ]);
    }

    public function all()
    {
        return $this->getTable()->order('created_at DESC');
    }

    public function active(): Selection
    {
        return $this->all()->where(['is_active' => true]);
    }

    public function findByUrlKey($urlKey)
    {
        return $this->getTable()->where('url_key', $urlKey)->fetch();
    }

    public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    public function incrementShows(IRow $funnel)
    {
        return $this->increment($funnel, 'total_show');
    }

    public function incrementConversions(IRow $funnel)
    {
        $this->update($funnel, ['last_conversion' => new DateTime()]);
        return $this->increment($funnel, 'total_conversions');
    }

    public function incrementErrors(IRow $funnel)
    {
        return $this->increment($funnel, 'total_errors');
    }

    private function increment(IRow $funnel, $field, $value = 1)
    {
        $date = new DateTime();
        return $this->getDatabase()->query("UPDATE sales_funnels SET {$field}={$field}+{$value}, last_use='{$date->format('Y-m-d H:i:s')}' WHERE id=" . $funnel->id);
    }

    public function totalPaidAmount(IRow $funnel)
    {
        return $this->getTable()
            ->where(':payments.status = ?', PaymentsRepository::STATUS_PAID)
            ->where(':payments.sales_funnel_id = ?', $funnel->id)
            ->sum(':payments.amount');
    }

    public function getSalesFunnelsBySubscriptionType(IRow $subscriptionType)
    {
        return $this->getTable()->where([':sales_funnels_subscription_types.subscription_type_id' => $subscriptionType->id]);
    }

    public function userSpentDistribution($funnelId, $levels)
    {
        $levelCount = count($levels);
        $result = array_fill(0, $levelCount, 0);

        $levelSelect = '';
        foreach ($levels as $i => $level) {
            if ($i+1 === count($levels)) {
                $levelSelect .= "\n  SUM(CASE WHEN amount >= {$level} THEN 1 ELSE 0 END) level{$i}";
                break;
            }
            $levelSelect .= "\n  SUM(CASE WHEN amount >= {$level} AND amount < {$levels[$i+1]} THEN 1 ELSE 0 END) level{$i},";
        }

        $sql = <<<SQL
SELECT $levelSelect FROM (
    SELECT funnel_paid.user_id, SUM(coalesce(old.amount, 0)) AS amount FROM (
        SELECT funnel_paid.user_id, MIN(funnel_paid.paid_at) as paid_at
        FROM payments AS funnel_paid
        WHERE funnel_paid.status='paid' AND funnel_paid.sales_funnel_id = $funnelId
        GROUP BY funnel_paid.user_id
    ) funnel_paid
    LEFT JOIN payments old ON old.status = 'paid' AND old.paid_at < funnel_paid.paid_at AND old.user_id=funnel_paid.user_id
    GROUP BY funnel_paid.user_id
) levels
SQL;

        $res = $this->getDatabase()->query($sql)->fetch();
        foreach ($levels as $i => $level) {
            $result[$i] = (int)$res['level'.$i];
        }

        return $result;
    }

    public function userSpentDistributionList($funnelId, $fromLevel, $toLevel)
    {
        if ($toLevel === 0) {
            $having = 'amount = 0';
        } elseif ($toLevel === null) {
            $having = 'amount >= ' . $fromLevel;
        } else {
            $having = 'amount >= ' . $fromLevel . ' AND amount < ' . $toLevel;
        }

        $sql = <<<SQL
SELECT users.* FROM (
    SELECT funnel_paid.user_id, SUM(coalesce(old.amount, 0)) AS amount FROM (
        SELECT funnel_paid.user_id, MIN(funnel_paid.paid_at) as paid_at
        FROM payments AS funnel_paid
        WHERE funnel_paid.status='paid' AND funnel_paid.sales_funnel_id = $funnelId
        GROUP BY funnel_paid.user_id
    ) funnel_paid
    LEFT JOIN payments old ON old.status = 'paid' AND old.paid_at < funnel_paid.paid_at AND old.user_id=funnel_paid.user_id
    GROUP BY funnel_paid.user_id
    HAVING $having
) levels
LEFT JOIN users ON users.id = levels.user_id
SQL;

        $res = $this->getDatabase()->query($sql)->fetchAll();
        return $res;
    }

    public function userPaymentsCountDistribution($funnelId, $levels)
    {
        $levelCount = count($levels);
        $result = array_fill(0, $levelCount, 0);

        $levelSelect = '';
        foreach ($levels as $i => $level) {
            if ($i+1 === count($levels)) {
                $levelSelect .= "SUM(CASE WHEN count >= {$level} THEN 1 ELSE 0 END) level{$i}";
                break;
            }
            $levelSelect .= "SUM(CASE WHEN count >= {$level} AND count < {$levels[$i+1]} THEN 1 ELSE 0 END) level{$i},";
        }

        $sql = <<<SQL
SELECT $levelSelect FROM (
    SELECT funnel_paid.user_id, COUNT(DISTINCT old.id) AS count
    FROM payments AS funnel_paid
    LEFT JOIN payments old ON old.status = 'paid' AND old.paid_at < funnel_paid.paid_at AND old.user_id=funnel_paid.user_id
    WHERE funnel_paid.status='paid' AND funnel_paid.sales_funnel_id = $funnelId
    GROUP BY funnel_paid.user_id
) levels
SQL;

        $res = $this->getDatabase()->query($sql)->fetch();
        foreach ($levels as $i => $level) {
            $result[$i] = (int)$res['level'.$i];
        }

        return $result;
    }

    public function userPaymentsCountDistributionList($funnelId, $fromLevel, $toLevel)
    {
        if ($fromLevel === 0) {
            $having = 'COUNT(DISTINCT old.id) = 0';
        } elseif (!$toLevel) {
            $having = 'COUNT(DISTINCT old.id) >= ' . $fromLevel;
        } else {
            $having = 'COUNT(DISTINCT old.id) >= ' . $fromLevel . ' AND COUNT(DISTINCT old.id) < ' . $toLevel;
        }

        $res = $this->getDatabase()->query("SELECT users.* FROM (
  SELECT user_id, COUNT(*) FROM (
    SELECT funnel_paid.user_id
    FROM payments AS funnel_paid
    LEFT JOIN payments old ON old.status = 'paid' AND old.paid_at < funnel_paid.paid_at AND old.user_id=funnel_paid.user_id
    WHERE funnel_paid.status='paid' AND funnel_paid.sales_funnel_id = $funnelId
    GROUP BY funnel_paid.user_id
    HAVING {$having}
  ) AS calc
  GROUP BY user_id
) sub
LEFT JOIN users ON users.id = sub.user_id")->fetchAll();

        return $res;
    }

    public function userSubscriptionsDistribution($funnelId, $levels)
    {
        return $this->subscriptionDaysDistribution->distribution($funnelId, $levels);
    }

    public function userSubscriptionsDistributionList($funnelId, $fromLevel, $toLevel)
    {
        return $this->subscriptionDaysDistribution->distributionList($funnelId, $fromLevel, $toLevel);
    }

    /**
     * @param IRow $funnel
     * @return array
     */
    public function getSalesFunnelSubscriptionTypes(IRow $funnel)
    {
        $subscriptionTypes = [];
        foreach ($funnel->related('sales_funnels_subscription_types') as $row) {
            $subscriptionTypes[] = $row->subscription_type;
        }

        return $subscriptionTypes;
    }

    public function getSalesFunnelDistribution(IRow $funnel)
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

    public function getSalesFunnelGateways(IRow $funnel)
    {
        $gateways = [];
        foreach ($funnel->related('sales_funnels_payment_gateways') as $row) {
            if ($row->payment_gateway->visible) {
                $gateways[] = $row->payment_gateway;
            }
        }
        return $gateways;
    }
}
