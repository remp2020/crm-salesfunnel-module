<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

/**
 * This widget renders listing of sales funnels for specific subscription type.
 * Used in subscription type detail.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class SubscriptionTypesInSalesFunnelsWidget extends BaseWidget
{
    private $templateName = 'subscription_types_in_sales_funnels_widget.latte';

    private $salesFunnelsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct($widgetManager);
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function header($id = '')
    {
        return 'Subscription Types in Sales Funnels';
    }

    public function identifier()
    {
        return 'subscriptiontypesinsalesfunnels';
    }

    public function render($subscriptionType)
    {
        $this->template->usedSalesFunnels = $this->salesFunnelsRepository->getSalesFunnelsBySubscriptionType($subscriptionType);
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
