<?php

namespace Crm\SalesFunnelModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class SalesFunnelTagsRepository extends Repository
{
    protected $tableName = 'sales_funnel_tags';

    final public function add(ActiveRow $salesFunnel, string $tagName): void
    {
        $this->insert([
            'sales_funnel_id' => $salesFunnel->id,
            'tag' => $tagName
        ]);
    }

    /**
     * @return string[]
     */
    final public function tagsSortedByOccurrences(): array
    {
        return $this->getTable()
            ->select("tag")
            ->group('tag')
            ->order('COUNT(*) DESC')
            ->order('tag ASC')
            ->fetchPairs('tag', 'tag');
    }

    final public function all(): Selection
    {
        return $this->getTable();
    }

    final public function removeTagsForSalesFunnel(ActiveRow $salesFunnel): void
    {
        $this->getTable()->where(['sales_funnel_id' => $salesFunnel->id])->delete();
    }

    final public function setTagsForSalesFunnel(ActiveRow $salesFunnel, array $tags): void
    {
        $this->database->transaction(function () use ($tags, $salesFunnel) {
            $this->removeTagsForSalesFunnel($salesFunnel);
            foreach ($tags as $tag) {
                $this->insert([
                    'sales_funnel_id' => $salesFunnel->id,
                    'tag' => $tag
                ]);
            }
        });
    }
}
