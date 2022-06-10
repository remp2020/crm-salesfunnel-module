<?php

namespace Crm\SalesFunnelModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SalesFunnelUpdatedEvent extends AbstractEvent
{
    private ActiveRow $oldSalesFunnel;
    private ActiveRow $salesFunnel;

    public function __construct(ActiveRow $oldSalesFunnel, ActiveRow $newSalesFunnel)
    {
        $this->oldSalesFunnel = $oldSalesFunnel;
        $this->salesFunnel = $newSalesFunnel;
    }

    public function getOldSalesFunnel(): ActiveRow
    {
        return $this->oldSalesFunnel;
    }

    public function getSalesFunnel(): ActiveRow
    {
        return $this->salesFunnel;
    }
}
