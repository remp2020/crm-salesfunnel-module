<?php

namespace Crm\SalesFunnelModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface TrackerDataProviderInterface extends DataProviderInterface
{
    public function provide(?array $params = []): array;
}
