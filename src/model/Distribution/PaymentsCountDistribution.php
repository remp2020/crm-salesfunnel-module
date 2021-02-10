<?php

namespace Crm\SalesFunnelModule\Distribution;

use Nette\Database\IRow;

class PaymentsCountDistribution extends AbstractFunnelDistribution
{
    public const TYPE = 'payments_count';

    protected function getDistributionRows($funnelId, $userId = null): array
    {
        $userSql = $this->getUserSql($userId);
        $statusSql = $this->getStatusSql();

        $sql = <<<SQL
SELECT sub.user_id,
       COUNT(payments.id) AS value
FROM (
         SELECT user_id, MIN(paid_at) as 'paid_at'
         FROM payments
         WHERE status IN $statusSql
           AND sales_funnel_id = $funnelId
         $userSql
         GROUP BY user_id
     ) sub
         LEFT JOIN payments
                   ON payments.paid_at < sub.paid_at AND
                      sub.user_id = payments.user_id AND
                      payments.status IN $statusSql
GROUP BY sub.user_id
SQL;

        return $this->database->query($sql)->fetchAll();
    }

    protected function prepareInsertRow(IRow $salesFunnel, IRow $distributionRow): array
    {
        return $this->salesFunnelsConversionDistributionsRepository
            ->prepareRow($salesFunnel->id, $distributionRow->user_id, self::TYPE, $distributionRow->value);
    }

    public function getDistributionList(int $funnelId, float $fromLevel, float $toLevel = null): array
    {
        $select = $this->salesFunnelsConversionDistributionsRepository
            ->salesFunnelTypeDistributions($funnelId, self::TYPE);

        if (is_null($toLevel)) {
            return $select->where('value >= ?', $fromLevel)->fetchAll();
        }

        if ($fromLevel === 0.0 && $toLevel === 0.0) {
            return $select->where('value = ?', 0)->fetchAll();
        }

        return $select->where('value >= ?', $fromLevel)->where('value < ?', $toLevel)->fetchAll();
    }
}
