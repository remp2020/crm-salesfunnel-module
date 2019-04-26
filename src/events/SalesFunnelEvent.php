<?php

namespace Crm\SalesFunnelModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;
use Nette\Security\User;

class SalesFunnelEvent extends AbstractEvent
{
    private $salesFunnel;

    private $type;

    private $email;

    public function __construct(IRow $salesFunnel, $user, $type)
    {
        $this->salesFunnel = $salesFunnel;
        $this->type = $type;
        $this->email = null;
        if ($user instanceof User) {
            $this->email = $user->isLoggedIn() ? $user->getIdentity()->email : null;
        } elseif ($user instanceof IRow) {
            $this->email = $user->email;
        }
    }

    public function getSalesFunnel()
    {
        return $this->salesFunnel;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getEmail()
    {
        return $this->email;
    }
}
