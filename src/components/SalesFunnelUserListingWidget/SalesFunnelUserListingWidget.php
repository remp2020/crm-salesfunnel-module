<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Nette\Database\Table\ActiveRow;

class SalesFunnelUserListingWidget extends BaseWidget
{
    private $templateName = 'sales_funnel_user_listing_widget.latte';

    public function identifier()
    {
        return 'salesfunneluserlistingwidget';
    }

    public function render(ActiveRow $payment)
    {
        if (!$payment->sales_funnel_id) {
            return;
        }

        $this->template->salesFunnel = $payment->sales_funnel;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
