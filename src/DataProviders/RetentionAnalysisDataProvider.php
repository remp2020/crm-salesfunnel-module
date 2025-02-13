<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\RetentionAnalysisDataProviderInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelTagsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;

class RetentionAnalysisDataProvider implements RetentionAnalysisDataProviderInterface
{
    public function __construct(
        private SalesFunnelsRepository $salesFunnelsRepository,
        private SalesFunnelTagsRepository $salesFunnelTagsRepository,
    ) {
    }

    /**
     * @param array $params
     *
     * @return Form
     * @throws DataProviderException
     */
    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }

        $form = $params['form'];
        $disable = $params['disable'] ?? false;
        $inputParams = $params['inputParams'];

        $salesFunnels = [];
        foreach ($this->salesFunnelsRepository->getTable()->fetchAll() as $row) {
            $salesFunnels[$row->id] = "$row->name <small>({$row->url_key})</small>";
        }
        $form->addMultiSelect('sales_funnel', 'sales_funnel.admin.dataprovider.retention_analysis.sales_funnel', $salesFunnels)
            ->setDisabled($disable)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $salesFunnelTags = $this->salesFunnelTagsRepository->tagsSortedByOccurrences();
        $form->addMultiSelect('sales_funnel_tag', 'sales_funnel.admin.dataprovider.retention_analysis.sales_funnel_tag', $salesFunnelTags)
            ->setDisabled($disable)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->setDefaults([
            'sales_funnel' => $inputParams['sales_funnel'] ?? [],
            'sales_funnel_tag' => $inputParams['sales_funnel_tag'] ?? [],
        ]);

        return $form;
    }

    public function filter(array &$wheres, array &$whereParams, array &$joins, array $inputParams): void
    {
        if (isset($inputParams['sales_funnel'])) {
            $placeholders = [];
            foreach ((array) $inputParams['sales_funnel'] as $salesFunnelId) {
                $whereParams[] = $salesFunnelId;
                $placeholders[] = '?';
            }

            $wheres[] = 'payments.sales_funnel_id IN (' . implode(',', $placeholders) . ')';
        }

        if (isset($inputParams['sales_funnel_tag'])) {
            $placeholders = [];
            foreach ((array) $inputParams['sales_funnel_tag'] as $salesFunnelTag) {
                $whereParams[] = $salesFunnelTag;
                $placeholders[] = '?';
            }

            $joins[] = "JOIN sales_funnel_tags ON payments.sales_funnel_id = sales_funnel_tags.sales_funnel_id";
            $wheres[] = 'sales_funnel_tags.tag IN (' . implode(',', $placeholders) . ')';
        }
    }
}
