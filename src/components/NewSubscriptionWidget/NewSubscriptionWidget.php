<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Nette\Application\BadRequestException;
use Nette\Http\Request;

class NewSubscriptionWidget extends BaseWidget
{
    private $templateName = 'new_subscription_widget.latte';

    private $request;

    private $salesFunnelsRepository;

    public function __construct(
        Request $request,
        SalesFunnelsRepository $salesFunnelsRepository,
        WidgetManager $widgetManager
    ) {
        parent::__construct($widgetManager);
        $this->request = $request;
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

        $refererUrl = $this->request->getReferer();
        $referer = '';
        if ($refererUrl) {
            $referer = $refererUrl->__toString();
        }

        $this->template->referer = $referer;
        $this->template->paymentGatewayId = null;
        $this->template->subscriptionTypeId = null;
        $this->template->utmSource = $this->getParameter('utm_source');
        $this->template->utmMedium = $this->getParameter('utm_medium');
        $this->template->utmCampaign = $this->getParameter('utm_campaign');
        $this->template->utmContent = $this->getParameter('utm_content');
        $this->template->bannerVariant = $this->getParameter('banner_variant');

        $paymentGatewayId = $this->getParameter('payment_gateway_id');
        if (isset($paymentGatewayId)) {
            $this->template->paymentGatewayId = intval($paymentGatewayId);
        }

        $subscriptionTypeId = $this->getParameter('subscription_type_id');
        if (isset($subscriptionTypeId)) {
            $this->template->subscriptionTypeId = intval($subscriptionTypeId);
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
