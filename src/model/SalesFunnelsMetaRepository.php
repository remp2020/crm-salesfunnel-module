<?php

namespace Crm\SalesFunnelModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\IRow;
use Nette\Utils\DateTime;

class SalesFunnelsMetaRepository extends Repository
{
    protected $tableName = 'sales_funnels_meta';

    public function add(IRow $salesFunnel, $key, $value)
    {
        $this->insert([
            'sales_funnel_id' => $salesFunnel->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function exists(IRow $salesFunnel, $key)
    {
        return $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->count('*') > 0;
    }

    public function updateValue(IRow $salesFunnel, $key, $value)
    {
        $salesFunnelMeta = $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->fetch();
        return parent::update($salesFunnelMeta, [
            'value' => $value,
            'updated_at' => new DateTime(),
        ]);
    }

    public function incrementValue(IRow $salesFunnel, $key, $value = 1)
    {
        return $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])
            ->update(['value+=' => $value, 'updated_at' => new DateTime()]);
    }

    public function get(IRow $salesFunnel, $key)
    {
        $row = $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->limit(1)->fetch();
        if ($row) {
            return $row->value;
        }
        return false;
    }

    public function all(IRow $salesFunnel)
    {
        return $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id])->fetchPairs('key', 'value');
    }
}
