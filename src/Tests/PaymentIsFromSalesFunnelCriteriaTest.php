<?php

namespace Crm\SalesFunnelModule\Tests;

use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\PaymentsModule\VariableSymbolInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Scenarios\PaymentIsFromSalesFunnelCriteria;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\UsersRepository;

class PaymentIsFromSalesFunnelCriteriaTest extends PaymentsTestCase
{
    /** @var SalesFunnelsRepository */
    private $salesFunnelRespository;

    public function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            PaymentsRepository::class,
            PaymentMetaRepository::class,
            PaymentItemsRepository::class,
            PaymentItemMetaRepository::class,
            PaymentGatewaysRepository::class,
            RecurrentPaymentsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypesMetaRepository::class,
            VariableSymbolInterface::class,
            UsersRepository::class,
            SalesFunnelsRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->salesFunnelRespository = $this->getRepository(SalesFunnelsRepository::class);
    }

    public function testRequiredAndIsFromSalesFunnel(): void
    {
        [$userRow, $payment, $salesFunnel] = $this->prepareData();

        $this->paymentsRepository->update($payment, [
            'sales_funnel_id' => $salesFunnel->id
        ]);

        $paymentSelection = $this->getPaymentSelection();

        $paymentIsFromSalesFunnelCriteria = $this->inject(PaymentIsFromSalesFunnelCriteria::class);
        $values = (object)['selection' => true];
        $paymentIsFromSalesFunnelCriteria->addConditions($paymentSelection, [
            PaymentIsFromSalesFunnelCriteria::KEY => $values
        ], $userRow);

        $this->assertNotNull($paymentSelection->fetch());
    }

    public function testRequiredAndIsNotFromSalesFunnel(): void
    {
        [$userRow, $payment, $salesFunnel] = $this->prepareData();

        $paymentSelection = $this->getPaymentSelection();

        $paymentIsFromSalesFunnelCriteria = $this->inject(PaymentIsFromSalesFunnelCriteria::class);
        $values = (object)['selection' => true];
        $paymentIsFromSalesFunnelCriteria->addConditions($paymentSelection, [
            PaymentIsFromSalesFunnelCriteria::KEY => $values
        ], $userRow);

        $this->assertNull($paymentSelection->fetch());
    }

    public function testNotRequiredAndIsFromSalesFunnel(): void
    {
        [$userRow, $payment, $salesFunnel] = $this->prepareData();

        $this->paymentsRepository->update($payment, [
            'sales_funnel_id' => $salesFunnel->id
        ]);

        $paymentSelection = $this->getPaymentSelection();

        $paymentIsFromSalesFunnelCriteria = $this->inject(PaymentIsFromSalesFunnelCriteria::class);
        $values = (object)['selection' => false];
        $paymentIsFromSalesFunnelCriteria->addConditions($paymentSelection, [
            PaymentIsFromSalesFunnelCriteria::KEY => $values
        ], $userRow);

        $this->assertNull($paymentSelection->fetch());
    }

    public function testNotRequiredAndIsNotFromSalesFunnel(): void
    {
        [$userRow, $payment, $salesFunnel] = $this->prepareData();

        $paymentSelection = $this->getPaymentSelection();

        $paymentIsFromSalesFunnelCriteria = $this->inject(PaymentIsFromSalesFunnelCriteria::class);
        $values = (object)['selection' => false];
        $paymentIsFromSalesFunnelCriteria->addConditions($paymentSelection, [
            PaymentIsFromSalesFunnelCriteria::KEY => $values
        ], $userRow);

        $this->assertNotNull($paymentSelection->fetch());
    }

    private function prepareData()
    {
        $userRow = $this->getUser();
        $payment = $this->createPayment('as90da09sdu');
        $salesFunnel = $this->salesFunnelRespository->add('test sales funnel', 'test_sales_funnel', '');

        return [$userRow, $payment, $salesFunnel];
    }

    private function getPaymentSelection()
    {
        return $this->paymentsRepository->getTable()->where('variable_symbol = "as90da09sdu"');
    }
}
