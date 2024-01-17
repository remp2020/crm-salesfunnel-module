<?php

namespace Crm\SalesFunnelModule\Components\NewSubscriptionWidget;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Application\BadRequestException;

/**
 * Widget that renders page with iframe containing sales funnels.
 *
 * @property FrontendPresenter $presenter
 * @package Crm\SalesFunnelModule\Components
 */
class NewSubscriptionWidget extends BaseLazyWidget
{
    private $templateName = 'new_subscription_widget.latte';

    private $salesFunnelsRepository;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        LazyWidgetManager $lazyWidgetManager
    ) {
        parent::__construct($lazyWidgetManager);

        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function header($id = '')
    {
        return 'New Subscription';
    }

    public function identifier()
    {
        return 'newsubscription';
    }

    public function render($funnel)
    {
        $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($funnel);
        if (!$salesFunnel) {
            throw new BadRequestException('invalid sales funnel urlKey: ' . $funnel);
        }
        $this->template->salesFunnel = $salesFunnel->url_key;

        $referer = $this->presenter->getReferer();

        $this->template->referer = $referer;
        $this->template->paymentGatewayId = null;
        $this->template->subscriptionTypeId = null;
        $this->template->rtmSource = $this->presenter->getParameter('rtm_source') ?? $this->presenter->getParameter('utm_source');
        $this->template->rtmMedium = $this->presenter->getParameter('rtm_medium') ?? $this->presenter->getParameter('utm_medium');
        $this->template->rtmCampaign = $this->presenter->getParameter('rtm_campaign') ?? $this->presenter->getParameter('utm_campaign');
        $this->template->rtmContent = $this->presenter->getParameter('rtm_content') ?? $this->presenter->getParameter('utm_content');
        $this->template->rtmVariant = $this->presenter->getParameter('rtm_variant') ?? $this->presenter->getParameter('banner_variant');

        $paymentGatewayId = $this->presenter->getParameter('payment_gateway_id');
        if (isset($paymentGatewayId)) {
            $this->template->paymentGatewayId = (int) $paymentGatewayId;
        }

        $subscriptionTypeId = $this->presenter->getParameter('subscription_type_id');
        if (isset($subscriptionTypeId)) {
            $this->template->subscriptionTypeId = (int) $subscriptionTypeId;
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
