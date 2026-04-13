<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCopyFromToSalesFunnels extends AbstractMigration
{
    public function up(): void
    {
        $this->table('sales_funnels')
            ->addColumn('copied_from_funnel_id', 'integer', ['null' => true])
            ->addForeignKey('copied_from_funnel_id', 'sales_funnels')
            ->update();
    }

    public function down(): void
    {
        $this->table('sales_funnels')
            ->dropForeignKey('copied_from_funnel_id')
            ->removeColumn('copied_from_funnel_id')
            ->update();
    }
}
