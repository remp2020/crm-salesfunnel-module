<?php

namespace Crm\SalesFunnelModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;

class SalesFunnelEvent extends AbstractEvent
{
    private $email;

    public function __construct(
        private ActiveRow $salesFunnel,
        $user,
        private $type,
        private $userAgent = null,
    ) {


        if ($user instanceof User) {
            $this->email = $user->isLoggedIn() ? $user->getIdentity()->email : null;
        } elseif ($user instanceof ActiveRow) {
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

    public function getUserAgent()
    {
        return $this->userAgent;
    }
}
