<?php

namespace Crm\SalesFunnelModule\Components\NewSubscriptionWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Application\BadRequestException;
use Nette\Application\LinkGenerator;

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
        LazyWidgetManager $lazyWidgetManager,
        private LinkGenerator $linkGenerator,
    ) {
        parent::__construct($lazyWidgetManager);

        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function header()
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

        $linkParams = $this->presenter->getRequest()?->getParameters() ?? [];

        $linkParams['funnel'] = $salesFunnel->url_key;
        $linkParams['referer'] = $this->presenter->getReferer();
        $linkParams['payment_gateway_id'] = null;
        $linkParams['subscription_type_id'] = null;

        $paymentGatewayId = $this->presenter->getParameter('payment_gateway_id');
        if (isset($paymentGatewayId)) {
            $linkParams['payment_gateway_id'] = (int) $paymentGatewayId;
        }

        $subscriptionTypeId = $this->presenter->getParameter('subscription_type_id');
        if (isset($subscriptionTypeId)) {
            $linkParams['subscription_type_id'] = (int) $subscriptionTypeId;
        }

        $this->template->link = $this->linkGenerator->link('SalesFunnel:SalesFunnelFrontend:show', $linkParams);

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
