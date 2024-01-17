<?php

namespace Crm\SalesFunnelModule\Components\FinishRegistrationWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;

/**
 * Simple registration success page widget.
 * Renders button to complete registration.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class FinishRegistrationWidget extends BaseLazyWidget
{
    private $templateName = 'finish_registration_widget.latte';

    public function identifier()
    {
        return 'finishregistrationwidget';
    }

    public function render($payment)
    {
        if (!$payment->referer) {
            return;
        }

        if ($payment->sales_funnel && strpos($payment->referer, $payment->sales_funnel->url_key) !== false) {
            // referer is just link to the sales funnel, we don't want to redirect there
            return;
        }

        $this->template->referer = $payment->referer;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
