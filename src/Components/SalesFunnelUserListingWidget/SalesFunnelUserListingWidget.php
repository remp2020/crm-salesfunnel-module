<?php

namespace Crm\SalesFunnelModule\Components\SalesFunnelUserListingWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Nette\Database\Table\ActiveRow;

/**
 * Simple listing item widget. Used in payments listing.
 * Shows sales funnel used for specific payment.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class SalesFunnelUserListingWidget extends BaseLazyWidget
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
