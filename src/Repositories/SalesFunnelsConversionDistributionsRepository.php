<?php

namespace Crm\SalesFunnelModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Selection;
use Nette\Database\Table\ActiveRow;

class SalesFunnelsConversionDistributionsRepository extends Repository
{
    protected $tableName = 'sales_funnels_conversion_distributions';

    final public function add(ActiveRow $salesFunnel, ActiveRow $user, string $type, float $value)
    {
        $this->insert([
            'sales_funnel_id' => $salesFunnel->id,
            'user_id' => $user->id,
            'value' => $value,
            'type' => $type,
            'created_at' => new \DateTime(),
        ]);
    }

    final public function salesFunnelTypeDistributions(int $salesFunnelId, string $type): Selection
    {
        return $this->getTable()->where([
            'sales_funnels_conversion_distributions.sales_funnel_id' => $salesFunnelId,
            'sales_funnels_conversion_distributions.type' => $type
        ]);
    }

    final public function salesFunnelUserTypeDistribution(int $salesFunnelId, int $userId, string $type): Selection
    {
        return $this->salesFunnelTypeDistributions($salesFunnelId, $type)->where('user_id', $userId);
    }

    final public function deleteSalesFunnelTypeDistributions(int $salesFunnelId, string $type): int
    {
        return $this->salesFunnelTypeDistributions($salesFunnelId, $type)->delete();
    }

    final public function prepareRow(int $salesFunnelId, int $userId, string $type, float $value): array
    {
        return [
            'sales_funnel_id' => $salesFunnelId,
            'user_id' => $userId,
            'value' => $value,
            'type' => $type,
            'created_at' => new \DateTime(),
        ];
    }
}
