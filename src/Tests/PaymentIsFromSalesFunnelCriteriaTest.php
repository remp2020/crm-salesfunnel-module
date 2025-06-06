<?php

namespace Crm\SalesFunnelModule\Tests;

use Crm\PaymentsModule\Models\VariableSymbolInterface;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Scenarios\PaymentIsFromSalesFunnelCriteria;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;

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
            PaymentMethodsRepository::class,
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
            'sales_funnel_id' => $salesFunnel->id,
        ]);

        $paymentSelection = $this->getPaymentSelection();

        $paymentIsFromSalesFunnelCriteria = $this->inject(PaymentIsFromSalesFunnelCriteria::class);
        $values = (object)['selection' => true];
        $paymentIsFromSalesFunnelCriteria->addConditions($paymentSelection, [
            PaymentIsFromSalesFunnelCriteria::KEY => $values,
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
            PaymentIsFromSalesFunnelCriteria::KEY => $values,
        ], $userRow);

        $this->assertNull($paymentSelection->fetch());
    }

    public function testNotRequiredAndIsFromSalesFunnel(): void
    {
        [$userRow, $payment, $salesFunnel] = $this->prepareData();

        $this->paymentsRepository->update($payment, [
            'sales_funnel_id' => $salesFunnel->id,
        ]);

        $paymentSelection = $this->getPaymentSelection();

        $paymentIsFromSalesFunnelCriteria = $this->inject(PaymentIsFromSalesFunnelCriteria::class);
        $values = (object)['selection' => false];
        $paymentIsFromSalesFunnelCriteria->addConditions($paymentSelection, [
            PaymentIsFromSalesFunnelCriteria::KEY => $values,
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
            PaymentIsFromSalesFunnelCriteria::KEY => $values,
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
