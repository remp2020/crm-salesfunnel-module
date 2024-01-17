<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\PaymentsModule\DataProvider\RetentionAnalysisDataProviderInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Application\UI\Form;

class RetentionAnalysisDataProvider implements RetentionAnalysisDataProviderInterface
{
    private $salesFunnelsRepository;

    public function __construct(SalesFunnelsRepository $salesFunnelsRepository)
    {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
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

        $form->addMultiSelect('sales_funnel', 'Funnel', $salesFunnels)
            ->setDisabled($disable)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->setDefaults([
            'sales_funnel' => $inputParams['sales_funnel'] ?? [],
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

            $wheres[] = 'sales_funnel_id IN (' . implode(',', $placeholders) . ')';
        }
    }
}
