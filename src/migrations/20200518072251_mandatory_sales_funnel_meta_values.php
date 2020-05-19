<?php

use Phinx\Migration\AbstractMigration;

class MandatorySalesFunnelMetaValues extends AbstractMigration
{
    public function change()
    {
        $this->query('DELETE FROM sales_funnels_meta WHERE value IS NULL');
        $this->table('sales_funnels_meta')
            ->changeColumn('value', 'string', ['null' => false])
            ->update();
    }
}
