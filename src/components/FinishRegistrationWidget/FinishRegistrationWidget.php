<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;

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
