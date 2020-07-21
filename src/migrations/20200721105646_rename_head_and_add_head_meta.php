<?php

use Phinx\Migration\AbstractMigration;

class RenameHeadAndAddHeadMeta extends AbstractMigration
{
    public function change()
    {
        $this->table('sales_funnels')
            ->renameColumn('head', 'head_script')
            ->addColumn('head_meta', 'text', ['null' => true, 'after' => 'no_access_html'])
            ->update();
    }
}
