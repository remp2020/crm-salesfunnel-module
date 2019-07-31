<?php

use Phinx\Migration\AbstractMigration;

class AddDeviceTypeToSalesFunnelStats extends AbstractMigration
{

    public function change()
    {
        $this->table('sales_funnels_stats')
            ->removeIndex(['sales_funnel_id', 'date', 'type'])
            ->addColumn('device_type', 'string', ['null' => true])
            ->addIndex(['sales_funnel_id', 'date', 'type', 'device_type'], ['name' => 'sales_funnels_stats_unique' , 'unique' => true])
            ->update();
    }
}
