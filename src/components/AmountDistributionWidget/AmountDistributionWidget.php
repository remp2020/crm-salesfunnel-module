<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

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

    private $salesFunnelsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct($widgetManager);
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function identifier()
    {
        return 'amountdistribution';
    }

    public function render($funnelId)
    {
        $levels = [0, 0.01, 3, 6, 10, 20, 50, 100, 200, 300];
        $distribution = $this->salesFunnelsRepository->userSpentDistribution($funnelId, $levels);

        $this->template->levels = $levels;
        $this->template->distribution = $distribution;
        $this->template->funnelId = $funnelId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
