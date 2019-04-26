<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

class PaymentDistributionWidget extends BaseWidget
{
    private $templateName = 'payment_distribution_widget.latte';

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
        return 'paymentdistribution';
    }

    public function render($funnelId)
    {
        $levels = [0, 1, 3, 5, 8, 13, 21, 34];
        $distribution = $this->salesFunnelsRepository->userPaymentsCountDistribution($funnelId, $levels);

        $this->template->levels = $levels;
        $this->template->distribution = $distribution;
        $this->template->funnelId = $funnelId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
