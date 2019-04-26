<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;

class SalesFunnelsSubscriptionTypesRepository extends Repository
{
    protected $tableName = 'sales_funnels_subscription_types';

    public function add(IRow $salesFunnel, IRow $subscriptionType)
    {
        return $this->insert([
            'sales_funnel_id' => $salesFunnel->id,
            'subscription_type_id' => $subscriptionType->id,
        ]);
    }

    public function findByBoth(IRow $salesFunnel, IRow $subscriptionType)
    {
        return $this->getTable()->where([
            'sales_funnel_id' => $salesFunnel->id,
            'subscription_type_id' => $subscriptionType->id,
        ])->fetch();
    }
}
