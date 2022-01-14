<?php

namespace Crm\SalesFunnelModule\Distribution;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

class SubscriptionDaysDistribution extends AbstractFunnelDistribution
{
    public const NO_SUBSCRIPTION = -2;
    public const ACTIVE_SUBSCRIPTION = -1;
    public const TYPE = 'last_subscription_days';

    protected function getDistributionRows($funnelId, $userId = null): array
    {
        $userSql = $this->getUserSql($userId);
        $statusSql = $this->getStatusSql();

        $sql = <<<SQL
SELECT user_id, MIN(diff) as value, MAX(additional) as additional FROM (
   SELECT sub.user_id,
          sub.paid_at,
          subscriptions.end_time,
          DATEDIFF(sub.paid_at, subscriptions.end_time) AS 'diff',
          subscriptions.end_time > sub.paid_at AS 'additional'
   FROM (
            SELECT user_id, MIN(paid_at) as 'paid_at'
            FROM payments
            WHERE status IN $statusSql
              AND sales_funnel_id = $funnelId
              $userSql
            GROUP BY user_id
        ) sub
            LEFT JOIN subscriptions
                      ON subscriptions.start_time < sub.paid_at AND
                         sub.user_id = subscriptions.user_id
) diffs GROUP BY user_id
SQL;

        return $this->database->query($sql)->fetchAll();
    }

    protected function prepareInsertRow(ActiveRow $salesFunnel, ArrayHash $distributionRow): array
    {
        if (is_null($distributionRow->value)) {
            return $this->salesFunnelsConversionDistributionsRepository
                ->prepareRow($salesFunnel->id, $distributionRow->user_id, self::TYPE, self::NO_SUBSCRIPTION);
        }

        if ($distributionRow->additional === 1) {
            return $this->salesFunnelsConversionDistributionsRepository
                ->prepareRow($salesFunnel->id, $distributionRow->user_id, self::TYPE, self::ACTIVE_SUBSCRIPTION);
        }

        return $this->salesFunnelsConversionDistributionsRepository
            ->prepareRow($salesFunnel->id, $distributionRow->user_id, self::TYPE, $distributionRow->value);
    }

    public function getDistributionList(int $funnelId, float $fromLevel, float $toLevel = null): array
    {
        $select = $this->salesFunnelsConversionDistributionsRepository
            ->salesFunnelTypeDistributions($funnelId, self::TYPE);

        if ($fromLevel === (float)self::ACTIVE_SUBSCRIPTION) {
            return $select->where('value', self::ACTIVE_SUBSCRIPTION)->fetchAll();
        }

        if ($fromLevel === (float)self::NO_SUBSCRIPTION) {
            return $select->where('value', self::NO_SUBSCRIPTION)->fetchAll();
        }

        $select->where('value >= ?', $fromLevel);
        if (isset($toLevel)) {
            $select->where('value < ?', $toLevel);
        }

        return $select->fetchAll();
    }

    public function getActiveSubscriptionsDistribution(int $funnelId)
    {
        return $this->salesFunnelsConversionDistributionsRepository->salesFunnelTypeDistributions($funnelId, self::TYPE)
            ->where('value', self::ACTIVE_SUBSCRIPTION)->select('COUNT(*) AS active_subscriptions')->fetch();
    }

    public function getNoSubscriptionsDistribution(int $funnelId)
    {
        return $this->salesFunnelsConversionDistributionsRepository->salesFunnelTypeDistributions($funnelId, self::TYPE)
            ->where('value', self::NO_SUBSCRIPTION)->select('COUNT(*) AS no_subscriptions')->fetch();
    }
}
