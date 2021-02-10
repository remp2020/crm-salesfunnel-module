<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Distribution\PaymentsCountDistribution;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

/**
 * This widget fetches stats from sales funnel repository
 * and renders table with levels as lines (distribution).
 *
 * Shows how many payments made before buying via this sales funnel.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class PaymentDistributionWidget extends BaseWidget
{
    private $templateName = 'payment_distribution_widget.latte';

    private $salesFunnelsRepository;

    private $paymentsCountDistribution;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsCountDistribution $paymentsCountDistribution,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct($widgetManager);
        $this->widgetManager = $widgetManager;
        $this->paymentsCountDistribution = $paymentsCountDistribution;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function identifier()
    {
        return 'paymentdistribution';
    }

    public function render($funnelId)
    {
        $levels = [0, 1, 3, 5, 8, 13, 21, 34];
        $distribution = $this->paymentsCountDistribution->getDistribution($funnelId);
        $isDistributionActual = $this->paymentsCountDistribution->isDistributionActual($funnelId);

        $this->template->levels = $levels;
        $this->template->distribution = $distribution;
        $this->template->isDistributionActual = $isDistributionActual;
        $this->template->funnelId = $funnelId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
