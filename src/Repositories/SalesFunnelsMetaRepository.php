<?php

namespace Crm\SalesFunnelModule\Repositories;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SalesFunnelsMetaRepository extends Repository
{
    protected $tableName = 'sales_funnels_meta';

    final public function add(ActiveRow $salesFunnel, $key, $value)
    {
        $this->insert([
            'sales_funnel_id' => $salesFunnel->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function exists(ActiveRow $salesFunnel, $key)
    {
        return $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->count('*') > 0;
    }

    final public function updateValue(ActiveRow $salesFunnel, $key, $value)
    {
        $salesFunnelMeta = $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->fetch();
        return $this->update($salesFunnelMeta, [
            'value' => $value,
            'updated_at' => new DateTime(),
        ]);
    }

    final public function incrementValue(ActiveRow $salesFunnel, $key, $value = 1)
    {
        return $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])
            ->update(['value+=' => $value, 'updated_at' => new DateTime()]);
    }

    final public function get(ActiveRow $salesFunnel, $key)
    {
        $row = $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->limit(1)->fetch();
        if ($row) {
            return $row->value;
        }
        return false;
    }

    final public function deleteValue(ActiveRow $salesFunnel, $key)
    {
        $row = $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id, 'key' => $key])->fetch();

        return $this->delete($row);
    }

    final public function all(ActiveRow $salesFunnel)
    {
        return $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id])->fetchPairs('key', 'value');
    }
}
