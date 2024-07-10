<?php

namespace Crm\SalesFunnelModule\Components\SalesFunnelsListingWidget;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SegmentModule\Models\Config\SegmentSlowRecalculateThresholdFactory;

class SalesFunnelsListingWidget extends BaseLazyWidget
{
    private string $templateName = 'sales_funnels_listing.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly ApplicationConfig $applicationConfig,
        private readonly SegmentSlowRecalculateThresholdFactory $segmentSlowRecalculateThresholdFactory,
    ) {
        parent::__construct($widgetManager);
    }

    public function render(array $funnels): void
    {
        $this->template->segmentSlowRecalculateThresholdInSeconds = $this->segmentSlowRecalculateThresholdFactory->build()->thresholdInSeconds;
        $this->template->defaultSalesFunnelUrlKey = $this->applicationConfig->get('default_sales_funnel_url_key');
        $this->template->funnels = $funnels;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
