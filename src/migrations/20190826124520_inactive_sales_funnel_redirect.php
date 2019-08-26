<?php

use Phinx\Migration\AbstractMigration;

class InactiveSalesFunnelRedirect extends AbstractMigration
{
    public function change()
    {
        $this->table('sales_funnels')
            ->addColumn('redirect_funnel_id', 'integer', ['null' => true])
            ->addForeignKey('redirect_funnel_id', 'sales_funnels')
            ->update();
    }
}
