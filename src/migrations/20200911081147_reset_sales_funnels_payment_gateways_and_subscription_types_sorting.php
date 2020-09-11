<?php

use Phinx\Migration\AbstractMigration;

class ResetSalesFunnelsPaymentGatewaysAndSubscriptionTypesSorting extends AbstractMigration
{
    public function up()
    {
        $this->query('UPDATE sales_funnels_payment_gateways SET sorting = id;');
        $this->query('UPDATE sales_funnels_subscription_types SET sorting = id;');
    }
}
