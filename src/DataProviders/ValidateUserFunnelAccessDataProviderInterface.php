<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface ValidateUserFunnelAccessDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{sales_funnel: ActiveRow, user: ActiveRow} $params
     * @return bool
     * @throws DataProviderException
     */
    public function provide(array $params): bool;
}
