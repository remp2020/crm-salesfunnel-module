<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Upgrade\Expander;
use Tomaj\Hermes\Emitter;

class FixUpgradePresenter extends FrontendPresenter
{
    const SALES_FUNNEL_UPGRADE_FIX = 'viac-extra';

    /** @var  Expander */
    private $expander;

    /** @var PaymentProcessor */
    private $paymentProcessor;

    /** @var Emitter @inject */
    public $emitter;

    public function __construct(
        Expander $expander,
        PaymentProcessor $paymentProcessor
    ) {
        parent::__construct();
        $this->expander = $expander;
        $this->paymentProcessor = $paymentProcessor;
        $this->expander->setFix(1.9);
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
        $this->emitter->emit(new HermesMessage('sales-funnel', [
            'type' => 'checkout',
            'user_id' => $user->id,
            'browser_id' => (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null),
            'source' => $this->trackingParams(),
            'sales_funnel_id' => self::SALES_FUNNEL_UPGRADE_FIX,
        ]));

        $service = $id;
        $subscriptionUpgrade = $this->expander->canUpgrade($service);
        $this->template->upgrade = $subscriptionUpgrade;
        $this->template->service = $service;
    }

    public function renderUpgrade()
    {
        $this->expander->setTrackingParams($this->trackingParams());
        $this->expander->setSalesFunnelId(self::SALES_FUNNEL_UPGRADE_FIX);

        $gatewayId = isset($this->params['payment_gateway']) ? intval($this->params['payment_gateway']) : null;

        try {
            $result = $this->expander->upgrade($this->params['service'], $this->params['subscription_type'], $gatewayId);
        } catch (\SoapFault $e) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.payment_gateway_timeout'), 'error');
            $this->redirect('Upgrade:error');
            return;
        }

        if ($result === true) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.success'));
            $this->redirect('Upgrade:success');
        } elseif ($result === false) {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
            $this->redirect('Upgrade:error');
        } elseif (is_a($result, 'Nette\Database\Table\ActiveRow')) {
            // taky jemny hack - ak sa nevari boolean ale vrati sa Irow ktory je paymenta
            // tak ju sprocesujeme
            try {
                $this->paymentProcessor->begin($result);
            } catch (CannotProcessPayment $err) {
                $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
                $this->redirect('Upgrade:error');
            }
        } else {
            $this->flashMessage($this->translator->translate('sales_funnel.frontend.upgrade.error.message'), 'error');
            $this->redirect('Upgrade:error');
        }
    }
}
