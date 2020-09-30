<?php

namespace Crm\SalesFunnelModule\DI;

class Config
{
    private $funnelRoutes;

    public function setFunnelRoutes(bool $funnelRoutes)
    {
        $this->funnelRoutes = $funnelRoutes;
    }

    public function getFunnelRoutes(): bool
    {
        return $this->funnelRoutes;
    }
}
