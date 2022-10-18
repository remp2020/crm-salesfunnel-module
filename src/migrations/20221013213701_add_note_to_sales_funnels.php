<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNoteToSalesFunnels extends AbstractMigration
{
    public function change(): void
    {
        $this->table('sales_funnels')
            ->addColumn('note', 'text', ['null' => true])
            ->update();
    }
}
