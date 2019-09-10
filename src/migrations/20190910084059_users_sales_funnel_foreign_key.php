<?php

use Phinx\Migration\AbstractMigration;

class UsersSalesFunnelForeignKey extends AbstractMigration
{
    public function change()
    {
        if (!$this->table('users')->hasForeignKey('sales_funnel_id')) {
            $this->table('users')
                ->addForeignKey('sales_funnel_id', 'sales_funnels')
                ->update();
        }
    }
}
