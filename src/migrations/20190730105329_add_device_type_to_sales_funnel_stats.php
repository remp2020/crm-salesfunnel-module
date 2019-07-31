<?php

use Phinx\Migration\AbstractMigration;

class AddDeviceTypeToSalesFunnelStats extends AbstractMigration
{

    public function change()
    {
        $this->table('sales_funnels_stats')
            ->addColumn('device_type', 'string', ['null' => true])
            ->update();

        $this->table('sales_funnels_stats')
            ->removeIndex(['sales_funnel_id', 'date', 'type'])
            ->addIndex(['sales_funnel_id', 'date', 'type', 'device_type'], ['name' => 'sales_funnel_id', 'unique' => true])
            ->update();
    }
}
