<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;

class SalesFunnelsSubscriptionTypesRepository extends Repository
{
    protected $tableName = 'sales_funnels_subscription_types';

    final public function add(IRow $salesFunnel, IRow $subscriptionType)
    {
        $data = [
            'sales_funnel_id' => $salesFunnel->id,
            'subscription_type_id' => $subscriptionType->id,
        ];

        $row = $this->getTable()->where($data)->fetch();
        if (!$row) {
            $row = $this->insert($data);
        }
        return $row;
    }

    final public function findByBoth(IRow $salesFunnel, IRow $subscriptionType)
    {
        return $this->getTable()->where([
            'sales_funnel_id' => $salesFunnel->id,
            'subscription_type_id' => $subscriptionType->id,
        ])->fetch();
    }
}
