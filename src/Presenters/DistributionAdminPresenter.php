<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\SalesFunnelModule\Components\AmountDistributionWidget\AmountDistributionWidgetFactory;
use Crm\SalesFunnelModule\Components\DaysFromLastSubscriptionDistributionWidget\DaysFromLastSubscriptionDistributionWidgetFactory;
use Crm\SalesFunnelModule\Components\PaymentDistributionWidget\PaymentDistributionWidgetFactory;
use Crm\SalesFunnelModule\Models\Distribution\PaymentsCountDistribution;
use Crm\SalesFunnelModule\Models\Distribution\PaymentsSumDistribution;
use Crm\SalesFunnelModule\Models\Distribution\SubscriptionDaysDistribution;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class DistributionAdminPresenter extends AdminPresenter
{
    private SalesFunnelsRepository $salesFunnelsRepository;

    private PaymentsSumDistribution $paymentsSumDistribution;

    private PaymentsCountDistribution $paymentsCountDistribution;

    private SubscriptionDaysDistribution $subscriptionDaysDistribution;

    private UsersRepository $usersRepository;

    public function __construct(
        PaymentsCountDistribution $paymentsCountDistribution,
        PaymentsSumDistribution $paymentsSumDistribution,
        SalesFunnelsRepository $salesFunnelsRepository,
        SubscriptionDaysDistribution $subscriptionDaysDistribution,
        UsersRepository $usersRepository
    ) {
        parent::__construct();
        $this->paymentsSumDistribution = $paymentsSumDistribution;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->paymentsCountDistribution = $paymentsCountDistribution;
        $this->subscriptionDaysDistribution = $subscriptionDaysDistribution;
        $this->usersRepository = $usersRepository;
    }

    /**
     * @admin-access-level read
     */
    public function renderAmount($funnelId, $fromLevel, $toLevel)
    {
        $distributionList = $this->paymentsSumDistribution->getDistributionList($funnelId, $fromLevel, $toLevel);
        $funnel = $this->salesFunnelsRepository->find($funnelId);

        $this->template->distributionList = $distributionList;
        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->funnel = $funnel;
    }

    public function createComponentAmountDistribution(AmountDistributionWidgetFactory $factory)
    {
        return $factory->create();
    }

    /**
     * @admin-access-level read
     */
    public function renderPayment($funnelId, $fromLevel, $toLevel)
    {
        $distributionList = $this->paymentsCountDistribution->getDistributionList($funnelId, $fromLevel, $toLevel);
        $funnel = $this->salesFunnelsRepository->find($funnelId);

        $this->template->distributionList = $distributionList;
        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->funnel = $funnel;
    }

    public function createComponentPaymentDistribution(PaymentDistributionWidgetFactory $factory)
    {
        return $factory->create();
    }

    /**
     * @admin-access-level read
     */
    public function renderDaysFromLastSubscription($funnelId, $fromLevel, $toLevel)
    {
        $distributionList = $this->subscriptionDaysDistribution->getDistributionList($funnelId, $fromLevel, $toLevel);
        $funnel = $this->salesFunnelsRepository->find($funnelId);

        $this->template->distributionList = $distributionList;
        $this->template->fromLevel = $fromLevel;
        $this->template->toLevel = $toLevel;
        $this->template->funnel = $funnel;
    }

    public function createComponentDaysFromLastSubscriptionDistribution(DaysFromLastSubscriptionDistributionWidgetFactory $factory)
    {
        return $factory->create();
    }
}
