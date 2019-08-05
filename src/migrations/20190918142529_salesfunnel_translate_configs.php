<?php

use Phinx\Migration\AbstractMigration;

class SalesfunnelTranslateConfigs extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            update configs set display_name = 'sales_funnel.config.default_sales_funnel_url_key.name' where name = 'default_sales_funnel_url_key';
            update configs set description = 'sales_funnel.config.default_sales_funnel_url_key.description' where name = 'default_sales_funnel_url_key';
        ");
    }

    public function down()
    {

    }
}
