<?php

namespace Crm\SalesFunnelModule\Distribution;

use Nette\Database\Context;

class SubscriptionDaysDistribution implements DistributionInterface
{
    private $database;

    public function __construct(Context $database)
    {
        $this->database = $database;
    }

    public function distribution(int $funnelId, array $levels): array
    {
        $levels[] = -1;
        $lastLevel = false;
        $result = [];
        foreach ($levels as $level) {
            $skeleton = $this->getQuerySkeleton($level, $lastLevel, $funnelId);
            $query = "SELECT COUNT(*) AS result FROM ({$skeleton}) AS sub";

            $res = $this->database->query($query)->fetch();
            $result[$level] = $res->result;
            $lastLevel = $level;
        }

        return $result;
    }

    public function distributionList(int $funnelId, float $fromLevel, float $toLevel = null): array
    {
        $skeleton = $this->getQuerySkeleton($toLevel, $fromLevel, $funnelId);
        $query = "SELECT users.* FROM ({$skeleton}) AS sub LEFT JOIN users ON sub.user_id = users.id";

        return $this->database->query($query)->fetchAll();
    }

    private function getQuerySkeleton(float $level, float $lastLevel, $funnelId)
    {
        if ($level === 0.0) {
            $join = 'subscriptions.start_time < funnel_paid.paid_at AND subscriptions.end_time > funnel_paid.paid_at';
            $where = 'subscriptions.id IS NOT NULL';
        } elseif ($level === -1.0) {
            $join = 'subscriptions.start_time < funnel_paid.paid_at AND subscriptions.end_time < funnel_paid.paid_at';
            $where = 'subscriptions.id IS NULL';
        } else {
            $join = "subscriptions.end_time BETWEEN funnel_paid.paid_at - INTERVAL {$level} DAY AND funnel_paid.paid_at - INTERVAL {$lastLevel} DAY";
            $where = 'subscriptions.id IS NOT NULL AND next_subscription.id IS NULL';
        }

        return <<<SQL
    SELECT DISTINCT funnel_paid.user_id FROM
    (
        SELECT user_id, MIN(paid_at) as paid_at
        FROM payments
        WHERE status='paid' AND sales_funnel_id = $funnelId
        GROUP BY user_id
    ) funnel_paid
    
    -- get full first payment of funnel and related subscription
    INNER JOIN payments
      ON payments.paid_at = funnel_paid.paid_at
      AND payments.user_id = funnel_paid.user_id
     
    -- get other subscriptions
    LEFT JOIN subscriptions
      ON subscriptions.user_id = funnel_paid.user_id 
      AND (payments.subscription_id IS NULL OR subscriptions.id != payments.subscription_id)
      AND {$join}
     
    -- make sure this is the last subscription  
    LEFT JOIN subscriptions AS next_subscription
      ON next_subscription.end_time > subscriptions.end_time
      AND next_subscription.user_id = subscriptions.user_id
      AND next_subscription.id != payments.subscription_id
      AND next_subscription.start_time < funnel_paid.paid_at
      
    WHERE {$where}
SQL;
    }
}
