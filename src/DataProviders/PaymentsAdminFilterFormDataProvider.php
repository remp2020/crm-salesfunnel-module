<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\PaymentsModule\DataProviders\AdminFilterFormDataProviderInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;

class PaymentsAdminFilterFormDataProvider implements AdminFilterFormDataProviderInterface
{
    private $salesFunnelsRepository;

    public function __construct(SalesFunnelsRepository $salesFunnelsRepository)
    {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    /**
     * @param array $params
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

        if (!isset($params['formData'])) {
            throw new DataProviderException('missing [formData] within data provider params');
        }
        if (!is_array($params['formData'])) {
            throw new DataProviderException('invalid type of provided formData: ' . get_class($params['formData']));
        }

        $form = $params['form'];
        $formData = $params['formData'];

        $salesFunnels = $this->salesFunnelsRepository->getTable()->fetchPairs('id', 'name');
        $form->addMultiSelect('sales_funnel', 'Funnel', $salesFunnels)
//            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->setDefaults([
            'sales_funnel' => $this->getSalesFunnel($formData),
        ]);

        return $form;
    }

    public function filter(Selection $selection, array $formData): Selection
    {
        if ($this->getSalesFunnel($formData)) {
            $selection->where('sales_funnel_id', $this->getSalesFunnel($formData));
        }
        return $selection;
    }

    private function getSalesFunnel($formData)
    {
        return $formData['sales_funnel'] ?? null;
    }
}
