<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLimitPerUserForPeriodToSalesFunnelsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('sales_funnels')
            ->addColumn('limit_per_user_for_period', 'integer', ['null' => true, 'after' => 'limit_per_user'])
            ->update();
    }

    public function down(): void
    {
        $this->table('sales_funnels')
            ->removeColumn('limit_per_user_for_period')
            ->update();
    }
}
