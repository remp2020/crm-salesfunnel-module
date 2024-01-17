<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface TrackerDataProviderInterface extends DataProviderInterface
{
    public function provide(?array $params = []): array;
}
