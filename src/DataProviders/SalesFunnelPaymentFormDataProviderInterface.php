<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;

interface SalesFunnelPaymentFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params);
}
