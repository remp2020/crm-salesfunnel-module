<?php

use Phinx\Migration\AbstractMigration;

class AddLimitPerUserColumnToSalesFunnelsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('sales_funnels')
            ->addColumn('limit_per_user', 'integer', ['null' => true])
            ->update();
    }
}
