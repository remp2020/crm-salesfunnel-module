<?php

namespace Crm\SalesFunnelModule\Events;

use Detection\MobileDetect;
use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;
use Nette\Security\User;

class SalesFunnelEvent extends AbstractEvent
{
    private $salesFunnel;

    private $type;

    private $email;

    private $deviceType = null;

    public function __construct(IRow $salesFunnel, $user, $type, $userAgent = null)
    {
        $this->salesFunnel = $salesFunnel;
        $this->type = $type;


        if ($userAgent) {
            $detector = new MobileDetect(null, $userAgent);
            // Check for tablet first since it's a subset of mobile
            if ($detector->isTablet()) {
                $this->deviceType = 'tablet';
            } elseif ($detector->isMobile()) {
                $this->deviceType = 'mobile';
            } else {
                $this->deviceType = 'desktop';
            }
        }

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

    public function getDeviceType()
    {
        return $this->deviceType;
    }
}
