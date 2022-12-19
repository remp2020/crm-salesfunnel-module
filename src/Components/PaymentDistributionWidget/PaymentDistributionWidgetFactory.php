<?php

namespace Crm\SalesFunnelModule\Components;

interface PaymentDistributionWidgetFactory
{
    public function create(): PaymentDistributionWidget;
}
