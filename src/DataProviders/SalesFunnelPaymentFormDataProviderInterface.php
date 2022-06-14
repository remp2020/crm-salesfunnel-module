<?php

namespace Crm\SalesFunnelModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface SalesFunnelPaymentFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params);
}
