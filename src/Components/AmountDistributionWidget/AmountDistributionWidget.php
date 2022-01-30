<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Distribution\PaymentsSumDistribution;

/**
 * This widget fetches stats from sales funnel repository
 * and renders table with levels as lines (distribution).
 *
 * Shows how much user paid before buying via this sales funnel.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class AmountDistributionWidget extends BaseWidget
{
    private $templateName = 'amount_distribution_widget.latte';

    private $paymentsSumDistribution;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsSumDistribution $paymentsSumDistribution
    ) {
        parent::__construct($widgetManager);
        $this->widgetManager = $widgetManager;
        $this->paymentsSumDistribution = $paymentsSumDistribution;
    }

    public function identifier()
    {
        return 'amountdistribution';
    }

    public function render($funnelId)
    {
        $distribution = $this->paymentsSumDistribution->getDistribution($funnelId);
        $isDistributionActual = $this->paymentsSumDistribution->isDistributionActual($funnelId);

        $this->template->levels = $this->paymentsSumDistribution->getDistributionConfiguration();
        $this->template->distribution = $distribution;
        $this->template->isDistributionActual = $isDistributionActual;
        $this->template->funnelId = $funnelId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
