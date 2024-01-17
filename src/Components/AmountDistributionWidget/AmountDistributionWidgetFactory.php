<?php

namespace Crm\SalesFunnelModule\Components\AmountDistributionWidget;

interface AmountDistributionWidgetFactory
{
    public function create(): AmountDistributionWidget;
}
