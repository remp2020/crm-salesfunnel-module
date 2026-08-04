<?php

namespace Crm\SalesFunnelModule\DI;

class Config
{
    private bool $funnelRoutes;

    /** @var int[] Day counts offered as period options in the sales funnel form. */
    private array $limitPerUserForPeriodOptions = [];

    public function setFunnelRoutes(bool $funnelRoutes): void
    {
        $this->funnelRoutes = $funnelRoutes;
    }

    public function getFunnelRoutes(): bool
    {
        return $this->funnelRoutes;
    }

    public function setLimitPerUserForPeriodOptions(array $limitPerUserForPeriodOptions): void
    {
        $this->limitPerUserForPeriodOptions = $limitPerUserForPeriodOptions;
    }

    /**
     * @return int[]
     */
    public function getLimitPerUserForPeriodOptions(): array
    {
        return $this->limitPerUserForPeriodOptions;
    }
}
