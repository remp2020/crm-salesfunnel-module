<?php

namespace Crm\SalesFunnelModule\Components\PaymentDistributionWidget;

interface PaymentDistributionWidgetFactory
{
    public function create(): PaymentDistributionWidget;
}
