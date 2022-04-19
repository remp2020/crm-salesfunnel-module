<?php

use Phinx\Migration\AbstractMigration;

class AddLoggedInNotLoggedInShowColumnsToSalesFunnelsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('sales_funnels')
            ->addColumn('loggedin_show', 'integer', ['null' => false, 'default' => 0, 'after' => 'total_show'])
            ->addColumn('notloggedin_show', 'integer', ['null' => false, 'default' => 0, 'after' => 'loggedin_show'])
            ->update();
    }
}
