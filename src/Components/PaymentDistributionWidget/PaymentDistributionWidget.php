<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SalesFunnelModule\Distribution\PaymentsCountDistribution;

/**
 * This widget fetches stats from sales funnel repository
 * and renders table with levels as lines (distribution).
 *
 * Shows how many payments made before buying via this sales funnel.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class PaymentDistributionWidget extends BaseLazyWidget
{
    private $templateName = 'payment_distribution_widget.latte';

    private $paymentsCountDistribution;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsCountDistribution $paymentsCountDistribution
    ) {
        parent::__construct($lazyWidgetManager);

        $this->paymentsCountDistribution = $paymentsCountDistribution;
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
