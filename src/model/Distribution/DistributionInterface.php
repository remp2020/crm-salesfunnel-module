<?php

namespace Crm\SalesFunnelModule\Distribution;

use Nette\Database\Context;

interface DistributionInterface
{
    public function distribution(Context $database, int $funnelId, array $levels): array;

    public function distributionList(Context $database, int $funnelId, float $fromLevel, float $toLevel = null): array;
}
