<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RecountPrepaidPaymentsForSalesFunnels extends AbstractMigration
{
    public function up()
    {
        $this->execute(<<<SQL
            UPDATE sales_funnels JOIN
                (SELECT COUNT(*) AS conversions, sales_funnel_id 
                    FROM payments
                    WHERE sales_funnel_id IS NOT NULL 
                        AND paid_at IS NOT NULL
                        AND status IN ('paid','prepaid','refund')
                GROUP BY sales_funnel_id) t ON sales_funnels.id = t.sales_funnel_id
            SET sales_funnels.total_conversions = t.conversions
SQL
        );
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
