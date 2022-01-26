<?php


namespace Crm\SalesFunnelModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class CalculateSalesFunnelConversionDistributionEvent extends AbstractEvent
{
    private $salesFunnel;

    public function __construct(IRow $salesFunnel)
    {
        $this->salesFunnel = $salesFunnel;
    }

    public function getSalesFunnel(): IRow
    {
        return $this->salesFunnel;
    }
}
