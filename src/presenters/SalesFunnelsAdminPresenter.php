<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\PaymentsModule\Components\LastPaymentsControlFactoryInterface;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Components\WindowPreviewControlFactoryInterface;
use Crm\SalesFunnelModule\Forms\SalesFunnelAdminFormFactory;
use Crm\SalesFunnelModule\Repository\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsStatsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\SubscriptionType;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SalesFunnelsAdminPresenter extends AdminPresenter
{
    private $salesFunnelsRepository;

    private $salesFunnelAdminFormFactory;

    private $salesFunnelsMetaRepository;

    private $salesFunnelsStatsRepository;

    private $paymentGatewaysRepository;

    private $paymentsRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        SalesFunnelAdminFormFactory $salesFunnelAdminFormFactory,
        SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        SalesFunnelsStatsRepository $salesFunnelsStatsRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        parent::__construct();
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->salesFunnelAdminFormFactory = $salesFunnelAdminFormFactory;
        $this->salesFunnelsMetaRepository = $salesFunnelsMetaRepository;
        $this->salesFunnelsStatsRepository = $salesFunnelsStatsRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    public function renderDefault()
    {
        $this->template->funnels = $this->salesFunnelsRepository->all();
    }

    public function renderNew()
    {
    }

    public function renderShow($id)
    {
        $funnel = $this->salesFunnelsRepository->find($id);
        if (!$funnel) {
            $this->flashMessage($this->translator->translate('sales_funnel.admin.sales_funnels.messages.sales_funnel_not_found'), 'danger');
            $this->redirect('default');
        }
        $this->template->funnel = $funnel;
        $this->template->total_paid_amount = $this->salesFunnelsRepository->totalPaidAmount($funnel);
        $this->template->subscriptionTypesPaymentsMap = $this->salesFunnelsRepository->getSalesFunnelDistribution($funnel);
        $this->template->meta = $this->salesFunnelsMetaRepository->all($funnel);

        $payments = $this->paymentsRepository->getTable()
            ->where(['status' => PaymentsRepository::STATUS_PAID, 'sales_funnel_id' => $funnel->id])
            ->order('paid_at DESC');

        $filteredCount = $this->template->filteredCount = $payments->count('*');
        $vp = new VisualPaginator();
        $this->addComponent($vp, 'paymentsvp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);

        $this->template->vp = $vp;
        $this->template->payments = $payments->limit($paginator->getLength(), $paginator->getOffset());
    }

    public function renderPreview($id)
    {
        $funnel = $this->salesFunnelsRepository->find($id);
        $this->template->funnel = $funnel;
    }

    public function renderEdit($id)
    {
        $this->template->funnel = $this->salesFunnelsRepository->find($id);
    }

    public function createComponentSalesFunnelForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = intval($this->params['id']);
        }

        $form = $this->salesFunnelAdminFormFactory->create($id);

        $this->salesFunnelAdminFormFactory->onSave = function ($funnel) {
            $this->flashMessage($this->translator->translate('sales_funnel.admin.sales_funnels.messages.funnel_created'));
            $this->redirect('show', $funnel->id);
        };
        $this->salesFunnelAdminFormFactory->onUpdate = function ($funnel) {
            $this->flashMessage($this->translator->translate('sales_funnel.admin.sales_funnels.messages.funnel_updated'));
            $this->redirect('show', $funnel->id);
        };
        return $form;
    }

    protected function createComponentPaymentGatewayForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

        $funnel = $this->salesFunnelsRepository->find($this->params['id']);
        $unavailableIds = array_keys($funnel->related('sales_funnels_payment_gateways')->fetchPairs('payment_gateway_id'));
        $where = [];
        if ($unavailableIds) {
            $where['id NOT IN ?'] = $unavailableIds;
        }

        $paymentGateway = $form->addSelect('payment_gateway_id', 'subscriptions.data.subscription_types.fields.name', $this->paymentGatewaysRepository->all()->where($where)->fetchPairs('id', 'name'))
            ->setRequired('subscriptions.data.subscription_types.required.name');
        $paymentGateway->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = function ($form, $values) use ($funnel) {
            $funnel->related('sales_funnels_payment_gateways')->insert([
                'payment_gateway_id' => $values->payment_gateway_id,
            ]);
            if ($this->isAjax()) {
                $this->redrawControl('paymentGatewayForm');
            } else {
                $this->redirect('show', $funnel->id);
            }
        };

        return $form;
    }

    public function handleRemovePaymentGateway($paymentGatewayId)
    {
        $funnel = $this->salesFunnelsRepository->find($this->params['id']);
        $funnel->related('sales_funnels_payment_gateways')->where([
            'payment_gateway_id' => $paymentGatewayId,
        ])->delete();
        if ($this->isAjax()) {
            $this->redrawControl('paymentGatewayForm');
        } else {
            $this->redirect('show', $funnel->id);
        }
    }

    protected function createComponentSubscriptionTypeForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

        $funnel = $this->salesFunnelsRepository->find($this->params['id']);
        $unavailableIds = array_keys($funnel->related('sales_funnels_subscription_types')->fetchPairs('subscription_type_id'));
        $where = [];
        if ($unavailableIds) {
            $where['id NOT IN ?'] = $unavailableIds;
        }

        // zmen nazvy
        $subscriptionTypes = SubscriptionType::getPairs($this->subscriptionTypesRepository->all()->where($where)) ;
        $subscriptionType = $form->addSelect('subscription_type_id', 'subscriptions.data.subscription_types.fields.name', $subscriptionTypes)
            ->setRequired('subscriptions.data.subscription_types.required.name');
        $subscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = function ($form, $values) use ($funnel) {
            $funnel->related('sales_funnels_subscription_types')->insert([
                'subscription_type_id' => $values->subscription_type_id,
            ]);
            if ($this->isAjax()) {
                $this->redrawControl('subscriptionTypesForm');
            } else {
                $this->redirect('show', $funnel->id);
            }
        };

        return $form;
    }

    public function handleRemoveSubscriptionType($subscriptionTypeId)
    {
        $funnel = $this->salesFunnelsRepository->find($this->params['id']);
        $funnel->related('sales_funnels_subscription_types')->where([
            'subscription_type_id' => $subscriptionTypeId,
        ])->delete();
        if ($this->isAjax()) {
            $this->redrawControl('subscriptionTypesForm');
        } else {
            $this->redirect('show', $funnel->id);
        }
    }

    protected function createComponentFunnelShowGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('sales_funnels_stats')
            ->setTimeField('date')
            ->setWhere('AND sales_funnel_id=' . intval($this->params['id']) . ' AND type=\'' . SalesFunnelsStatsRepository::TYPE_SHOW . '\'')
            ->setValueField('SUM(value)')
            ->setStart('-1 month'))
            ->setName('Show');

        return $factory->create()
            ->setGraphTitle('Sales funnel show stats')
            ->setGraphHelp('Show stats')
            ->addGraphDataItem($graphDataItem)
        ;
    }

    protected function createComponentFunnelGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $graph = $factory->create()
            ->setGraphTitle('Sales funnel stats')
            ->setGraphHelp('All sales funnel stats');

        $types = $this->salesFunnelsStatsRepository->getTable()
            ->select('type')
            ->where(['sales_funnel_id' => $this->params['id']])
            ->group('type')
            ->fetchAll();

        /** @var ActiveRow $row */
        foreach ($types as $row) {
            $graphDataItem = new GraphDataItem();
            $graphDataItem->setCriteria((new Criteria())
                ->setTableName('sales_funnels_stats')
                ->setTimeField('date')
                ->setWhere('AND sales_funnel_id=' . intval($this->params['id']) . ' AND type=\'' . $row->type . '\'')
                ->setValueField('SUM(value)')
                ->setStart('-1 month'))
                ->setName($row->type);

            $graph->addGraphDataItem($graphDataItem);
        }

        return $graph;
    }

    protected function createComponentPaymentGatewaysGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('modified_at')
            ->setWhere("AND payments.status = 'paid' AND payments.sales_funnel_id=" . intval($this->params['id']))
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways on payment_gateways.id = payments.payment_gateway_id')
            ->setSeries('payment_gateways.name')
            ->setValueField('count(*)')
            ->setStart((new DateTime())->modify('-1 month')->format('Y-m-d'))
            ->setEnd((new DateTime())->format('Y-m-d')));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.gateways.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.gateways.tooltip'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    protected function createComponentSubscriptionsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();

        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setGroupBy('payment_items.name')
            ->setJoin(
                "LEFT JOIN payment_items ON payment_id = payments.id"
            )
            ->setWhere('AND payments.sales_funnel_id=' . intval($this->params['id']) . ' AND payments.status=\'paid\'')
            ->setSeries('payment_items.name')
            ->setValueField('sum(payment_items.count)')
            ->setStart((new DateTime())->modify('-1 month')->format('Y-m-d'))
            ->setEnd((new DateTime())->format('Y-m-d')));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('sales_funnel.admin.component.subscriptions_graph.title'))
            ->setGraphHelp($this->translator->translate('sales_funnel.admin.component.subscriptions_graph.help'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentLastPayments(LastPaymentsControlFactoryInterface $factory)
    {
        $control = $factory->create();
        $control->setSalesFunnelId($this->params['id'])
            ->setLimit(5);
        return $control;
    }

    public function createComponentWindowPreview(WindowPreviewControlFactoryInterface $factory)
    {
        $control = $factory->create();
        $control->setSalesFunnelId($this->params['id']);
        return $control;
    }
}
