<?php

use Phinx\Migration\AbstractMigration;

class AddSalesFunnelsConversionDistributions extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('sales_funnels_conversion_distributions');
        $table->addColumn('sales_funnel_id', 'integer')
            ->addColumn('type', 'string')
            ->addColumn('user_id', 'integer')
            ->addColumn('value', 'decimal', ['scale' => 2, 'precision' => '10', 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('sales_funnel_id', 'sales_funnels')
            ->addForeignKey('user_id', 'users')
            ->addIndex(['sales_funnel_id', 'type', 'user_id'], ['unique' => true, 'name' => 'funnel_type_user_idx'])
            ->create();
    }
}
