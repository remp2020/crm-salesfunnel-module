<?php

namespace Crm\SalesFunnelModule\Distribution;

interface DistributionInterface
{
    public function distribution(int $funnelId, array $levels): array;

    public function distributionList(int $funnelId, float $fromLevel, float $toLevel = null): array;
}
