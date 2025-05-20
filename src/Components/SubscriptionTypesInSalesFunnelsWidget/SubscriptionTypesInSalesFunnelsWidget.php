<?php

namespace Crm\SalesFunnelModule\Components\SubscriptionTypesInSalesFunnelsWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;

/**
 * This widget renders listing of sales funnels for specific subscription type.
 * Used in subscription type detail.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class SubscriptionTypesInSalesFunnelsWidget extends BaseLazyWidget
{
    private $templateName = 'subscription_types_in_sales_funnels_widget.latte';

    private $salesFunnelsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SalesFunnelsRepository $salesFunnelsRepository,
    ) {
        parent::__construct($lazyWidgetManager);
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
