<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

class DaysFromLastSubscriptionDistributionWidget extends BaseWidget
{
    private $templateName = 'days_from_last_subscription_distribution_widget.latte';

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
        return 'daysfromlastsubscription';
    }

    public function render($funnelId)
    {
        $levels = [0, 14, 30, 60, 120, 180, 99999];
        $distribution = $this->salesFunnelsRepository->userSubscriptionsDistribution($funnelId, $levels);

        $this->template->levels = $levels;
        $this->template->distribution = $distribution;
        $this->template->funnelId = $funnelId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
