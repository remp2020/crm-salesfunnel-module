<?php

namespace Crm\SalesFunnelModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;

class SalesFunnelsPaymentGatewaysRepository extends Repository
{
    protected $tableName = 'sales_funnels_payment_gateways';

    final public function add(ActiveRow $salesFunnel, ActiveRow $paymentGateway)
    {
        $data = [
            'sales_funnel_id' => $salesFunnel->id,
            'payment_gateway_id' => $paymentGateway->id,
        ];

        $row = $this->getTable()->where($data)->fetch();
        if (!$row) {
            $sorting = $this->getTable()
                ->where('sales_funnel_id = ?', $salesFunnel->id)
                ->group('sales_funnel_id')
                ->fetchField('MAX(`sorting`)');
            $data['sorting'] = $sorting + 1;
            $row = $this->insert($data);
        }
        return $row;
    }

    final public function findByBoth(ActiveRow $salesFunnel, ActiveRow $paymentGateway)
    {
        return $this->getTable()->where([
            'sales_funnel_id' => $salesFunnel->id,
            'payment_gateway_id' => $paymentGateway->id,
        ])->fetch();
    }

    final public function findAllBySalesFunnel(ActiveRow $salesFunnel)
    {
        return $salesFunnel->related('sales_funnels_payment_gateways')->order('sorting ASC')->fetchAll();
    }
}
