<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\IRow;
use Nette\Utils\DateTime;

class SalesFunnelsStatsRepository extends Repository
{
    const TYPE_SHOW       = 'show';
    const TYPE_FORM       = 'form';
    const TYPE_NO_ACCESS  = 'no_access';
    const TYPE_ERROR      = 'error';
    const TYPE_OK         = 'ok';

    protected $tableName = 'sales_funnels_stats';
    
    final public function add(
        IRow $salesFunnel,
        $type,
        $deviceType,
        DateTime $date = null,
        $value = 1
    ) {
        if ($date == null) {
            $date = DateTime::from(strtotime('today 00:00'));
        }

        $this->getDatabase()->query(
            'INSERT INTO sales_funnels_stats (sales_funnel_id,date,type,device_type,value) ' .
            " VALUES ({$salesFunnel->id},'{$date->format('Y-m-d H:i:s')}','{$type}', '{$deviceType}', {$value}) " .
            " ON DUPLICATE KEY UPDATE value=value+{$value}"
        );
    }
}
