<?php

namespace Crm\SalesFunnelModule\Components;

interface PaymentDistributionWidgetFactory
{
    /** @return PaymentDistributionWidget */
    public function create();
}
