<?php

namespace Crm\SalesFunnelModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface AddressFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;
}
