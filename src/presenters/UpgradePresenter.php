<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Upgrade\Expander;
use Tomaj\Hermes\Emitter;

class UpgradePresenter extends FrontendPresenter
{
    const SALES_FUNNEL_UPGRADE = 'viac';
    const SALES_FUNNEL_UPGRADE_MONTH = 'viac-akcia';

    public $hermesEmitter;

    private $expander;

    private $paymentProcessor;

    public function __construct(
        Expander $expander,
        PaymentProcessor $paymentProcessor,
        Emitter $emitter
    ) {
        parent::__construct();
        $this->expander = $expander;
        $this->paymentProcessor = $paymentProcessor;
        $this->hermesEmitter = $emitter;
    }

    public function startup()
    {
        parent::startup();
        if ($this->layoutManager->exists($this->getLayoutName() . '_plain')) {
            $this->setLayout($this->getLayoutName() . '_plain');
        } else {
            $this->setLayout('sales_funnel_plain');
        }
    }

    public function renderSubscription($id = 'mobile')
    {
        $user = $this->getUser();
        $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
            'type' => 'checkout',
            'user_id' => $user->id,
            'browser_id' => (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null),
            'source' => $this->trackingParams(),
            'sales_funnel_id' => self::SALES_FUNNEL_UPGRADE,
        ]));

        $service = $id;
        $subscriptionUpgrade = $this->expander->canUpgrade($service);
        $this->template->upgrade = $subscriptionUpgrade;
        $this->template->service = $service;
    }

    public function renderUpgrade()
    {
        $this->expander->setTrackingParams($this->trackingParams());
        $this->expander->setSalesFunnelId(self::SALES_FUNNEL_UPGRADE);

        $gatewayId = isset($this->params['payment_gateway']) ? intval($this->params['payment_gateway']) : null;

        try {
            $result = $this->expander->upgrade($this->params['service'], $this->params['subscription_type'], $gatewayId);
        } catch (\SoapFault $e) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.payment_gateway_timeout'), 'error');
            $this->redirect('error');
            return;
        }

        if ($result === true) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.success'));
            $this->redirect('success');
        } elseif ($result === false) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
            $this->redirect('error');
        } elseif (is_a($result, 'Nette\Database\Table\ActiveRow')) {
            // taky jemny hack - ak sa nevari boolean ale vrati sa Irow ktory je paymenta
            // tak ju sprocesujeme
            try {
                $this->getPaymentConfig($result);
                $url = $this->paymentProcessor->begin($result);
                $this->redirectUrl($url);
            } catch (CannotProcessPayment $err) {
                $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'));
                $this->redirect('error');
            }
        } else {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
            $this->redirect('error');
        }
    }

    public function renderMonth()
    {
        $user = $this->getUser();
        $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
            'type' => 'checkout',
            'user_id' => $user->id,
            'browser_id' => (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null),
            'source' => $this->trackingParams(),
            'sales_funnel_id' => self::SALES_FUNNEL_UPGRADE_MONTH,
        ]));

        $service = 'mobile';
        $this->expander->setAction(true);
        $subscriptionUpgrade = $this->expander->canUpgrade($service);
        $this->template->upgrade = $subscriptionUpgrade;
        $this->template->service = $service;
    }

    public function renderMonthUpgrade()
    {
        $this->expander->setTrackingParams($this->trackingParams());
        $this->expander->setSalesFunnelId(self::SALES_FUNNEL_UPGRADE_MONTH);

        $this->expander->setAction(true);
        $gatewayId = isset($this->params['payment_gateway']) ? intval($this->params['payment_gateway']) : null;

        $result = $this->expander->upgrade($this->params['service'], $this->params['subscription_type'], $gatewayId);

        if ($result === true) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.success'));
            $this->redirect('success');
        } elseif ($result === false) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
            $this->redirect('error');
        } elseif (is_a($result, 'Nette\Database\Table\ActiveRow')) {
            // taky jemny hack - ak sa nevari boolean ale vrati sa Irow ktory je paymenta
            // tak ju sprocesujeme
            try {
                $this->getPaymentConfig($result);
                $url = $this->paymentProcessor->begin($result);
                $this->redirectUrl($url);
            } catch (CannotProcessPayment $err) {
                $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'));
                $this->redirect('error');
            }
        } else {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
            $this->redirect('error');
        }
    }

    public function renderSuccess()
    {
        $this->setLayout($this->getLayoutName());
    }

    public function renderError()
    {
        $this->setLayout($this->getLayoutName());
    }
}
