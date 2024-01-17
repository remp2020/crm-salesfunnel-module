<?php

namespace Crm\SalesFunnelModule\Models\Distribution;

interface DistributionInterface
{
    public function distribution(int $funnelId, array $levels): array;
}
