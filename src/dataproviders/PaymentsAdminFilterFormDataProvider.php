<?php

namespace Crm\SalesFunnelModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\PaymentsModule\DataProvider\AdminFilterFormDataProviderInterface;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Nette\Application\Request;
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

        if (!isset($params['request'])) {
            throw new DataProviderException('missing [request] within data provider params');
        }
        if (!($params['request'] instanceof Request)) {
            throw new DataProviderException('invalid type of provided request: ' . get_class($params['request']));
        }

        $form = $params['form'];
        $request = $params['request'];

        $salesFunnels = $this->salesFunnelsRepository->getTable()->fetchPairs('id', 'name');
        $form->addMultiSelect('sales_funnel', 'Funnel', $salesFunnels)
//            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->setDefaults([
            'sales_funnel' => $request->getParameter('sales_funnel'),
        ]);

        return $form;
    }

    public function filter(Selection $selection, Request $request): Selection
    {
        if ($request->getParameter('sales_funnel')) {
            $selection->where('sales_funnel_id', $request->getParameter('sales_funnel'));
        }
        return $selection;
    }
}
