<?php

use Phinx\Migration\AbstractMigration;

class AddDeviceTypeToSalesFunnelStats extends AbstractMigration
{

    public function change()
    {
        $this->table('sales_funnels_stats')
            ->addColumn('device_type', 'text', ['null' => true])
            ->update();
    }
}
