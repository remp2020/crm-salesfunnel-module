<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SalesFunnelsStatsRepository extends Repository
{
    public const TYPE_SHOW = 'show';
    public const TYPE_FORM = 'form';
    public const TYPE_NO_ACCESS = 'no_access';
    public const TYPE_ERROR = 'error';
    public const TYPE_OK = 'ok';

    protected $tableName = 'sales_funnels_stats';

    public static function isAllowedType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_SHOW,
            self::TYPE_ERROR,
            self::TYPE_NO_ACCESS,
            self::TYPE_OK,
            self::TYPE_FORM,
        ], true);
    }

    final public function add(
        ActiveRow $salesFunnel,
        $type,
        $deviceType,
        DateTime $date = null,
        $value = 1
    ) {
        if ($date == null) {
            $date = DateTime::from(strtotime('today 00:00'));
        }

        if (!self::isAllowedType($type)) {
            return;
        }

        $sql = <<<SQL
            INSERT INTO sales_funnels_stats (`sales_funnel_id`, `date`, `type`, `device_type`, `value`)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE value = value + ? 
        SQL;

        $this->getDatabase()->query(
            $sql,
            $salesFunnel->id,
            $date->format('Y-m-d H:i:s'),
            $type,
            $deviceType,
            $value,
            $value
        );
    }
}
