<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\SalesFunnelModule\Components\AmountDistributionWidgetFactory;
use Crm\SalesFunnelModule\Components\DaysFromLastSubscriptionDistributionWidgetFactory;
use Crm\SalesFunnelModule\Components\PaymentDistributionWidgetFactory;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

class DistributionAdminPresenter extends AdminPresenter
{
    private $salesFunnelsRepository;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct();
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function renderAmount($funnelId, $fromLevel, $toLevel)
    {
        $this->template->users = $this->salesFunnelsRepository->userSpentDistributionList($funnelId, $fromLevel, $toLevel);

        $funnel = $this->salesFunnelsRepository->find($funnelId);

        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->funnel = $funnel;
    }

    public function createComponentAmountDistribution(AmountDistributionWidgetFactory $factory)
    {
        return $factory->create();
    }

    public function renderPayment($funnelId, $fromLevel, $toLevel)
    {
        $this->template->users = $this->salesFunnelsRepository->userPaymentsCountDistributionList($funnelId, $fromLevel, $toLevel);

        $funnel = $this->salesFunnelsRepository->find($funnelId);

        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->funnel = $funnel;
    }

    public function createComponentPaymentDistribution(PaymentDistributionWidgetFactory $factory)
    {
        return $factory->create();
    }

    public function renderDaysFromLastSubscription($funnelId, $fromLevel, $toLevel)
    {
        $this->template->users = $this->salesFunnelsRepository->userSubscriptionsDistributionList($funnelId, $fromLevel, $toLevel);

        $funnel = $this->salesFunnelsRepository->find($funnelId);

        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->funnel = $funnel;
    }

    public function createComponentDaysFromLastSubscriptionDistribution(DaysFromLastSubscriptionDistributionWidgetFactory $factory)
    {
        return $factory->create();
    }
}
