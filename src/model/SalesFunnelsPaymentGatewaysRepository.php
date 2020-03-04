<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;

class SalesFunnelsPaymentGatewaysRepository extends Repository
{
    protected $tableName = 'sales_funnels_payment_gateways';

    final public function add(IRow $salesFunnel, IRow $paymentGateway)
    {
        $data = [
            'sales_funnel_id' => $salesFunnel->id,
            'payment_gateway_id' => $paymentGateway->id,
        ];

        $row = $this->getTable()->where($data)->fetch();
        if (!$row) {
            $row = $this->insert($data);
        }
        return $row;
    }

    final public function findByBoth(IRow $salesFunnel, IRow $paymentGateway)
    {
        return $this->getTable()->where([
            'sales_funnel_id' => $salesFunnel->id,
            'payment_gateway_id' => $paymentGateway->id,
        ])->fetch();
    }
}
