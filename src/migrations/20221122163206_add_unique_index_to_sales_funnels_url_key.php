<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUniqueIndexToSalesFunnelsUrlKey extends AbstractMigration
{
    public function up()
    {
        $q = <<<SQL
            SELECT url_key, COUNT(*) 
            FROM sales_funnels 
            GROUP BY url_key HAVING COUNT(*) > 1
SQL;

        if (count($this->fetchAll($q)) > 0) {
            throw new Exception("Unable to add unique index to 'sales_funnels' column 'url_key' since there are duplicate values.");
        }

        $this->table('sales_funnels')
            ->removeIndex('url_key')
            ->addIndex('url_key', ['unique' => true])
            ->update();
    }

    public function down()
    {
        $this->table('sales_funnels')
            ->removeIndex('url_key')
            ->addIndex('url_key')
            ->update();
    }
}
