<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;

/**
 * Simple registration success page widget.
 * Renders button to complete registration.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class FinishRegistrationWidget extends BaseWidget
{
    private $templateName = 'finish_registration_widget.latte';

    public function identifier()
    {
        return 'finishregistrationwidget';
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
