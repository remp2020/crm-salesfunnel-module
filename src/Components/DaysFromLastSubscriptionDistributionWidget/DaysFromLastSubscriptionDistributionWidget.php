<?php

namespace Crm\SalesFunnelModule\Components\DaysFromLastSubscriptionDistributionWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SalesFunnelModule\Models\Distribution\SubscriptionDaysDistribution;

/**
 * This widget fetches stats from sales funnel repository
 * and renders table with levels as lines (distribution).
 *
 * Shows days since ending of last subscription (pre-payment)
 *
 * @package Crm\SalesFunnelModule\Components
 */
class DaysFromLastSubscriptionDistributionWidget extends BaseLazyWidget
{
    private $templateName = 'days_from_last_subscription_distribution_widget.latte';

    private $subscriptionDaysDistribution;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SubscriptionDaysDistribution $subscriptionDaysDistribution
    ) {
        parent::__construct($lazyWidgetManager);

        $this->subscriptionDaysDistribution = $subscriptionDaysDistribution;
    }

    public function identifier()
    {
        return 'daysfromlastsubscription';
    }

    public function render($funnelId)
    {
        $distribution = $this->subscriptionDaysDistribution->getDistribution($funnelId);
        $activeSubscriptions = $this->subscriptionDaysDistribution->getActiveSubscriptionsDistribution($funnelId);
        $noSubscriptions = $this->subscriptionDaysDistribution->getNoSubscriptionsDistribution($funnelId);
        $isDistributionActual = $this->subscriptionDaysDistribution->isDistributionActual($funnelId);

        $this->template->levels = $this->subscriptionDaysDistribution->getDistributionConfiguration();
        $this->template->activeSubscriptions = $activeSubscriptions->active_subscriptions;
        $this->template->noSubscriptions = $noSubscriptions->no_subscriptions;
        $this->template->distribution = $distribution;
        $this->template->isDistributionActual = $isDistributionActual;
        $this->template->funnelId = $funnelId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
