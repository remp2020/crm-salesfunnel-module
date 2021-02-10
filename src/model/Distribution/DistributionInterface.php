<?php

namespace Crm\SalesFunnelModule\Distribution;

interface DistributionInterface
{
    public function distribution(int $funnelId, array $levels): array;
}
