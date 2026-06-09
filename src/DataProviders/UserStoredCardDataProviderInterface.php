<?php
declare(strict_types=1);

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface UserStoredCardDataProviderInterface extends DataProviderInterface
{
    public function canUserUseStoredCard(ActiveRow $payment, ActiveRow $user): bool;
}
