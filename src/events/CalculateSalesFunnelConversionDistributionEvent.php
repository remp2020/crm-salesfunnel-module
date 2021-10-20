<?php


namespace Crm\SalesFunnelModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class CalculateSalesFunnelConversionDistributionEvent extends AbstractEvent
{
    private $salesFunnel;

    public function __construct(ActiveRow $salesFunnel)
    {
        $this->salesFunnel = $salesFunnel;
    }

    public function getSalesFunnel(): ActiveRow
    {
        return $this->salesFunnel;
    }
}
