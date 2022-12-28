<?php

namespace Crm\SalesFunnelModule\Tests;

use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Scenarios\PaymentIsFromSpecificSalesFunnelCriteria;

class PaymentIsFromSpecificSalesFunnelCriteriaTest extends PaymentsTestCase
{
    /** @var SalesFunnelsRepository */
    private $salesFunnelsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->salesFunnelsRepository = $this->getRepository(SalesFunnelsRepository::class);
    }

    public function requiredRepositories(): array
    {
        $repositories = parent::requiredRepositories();
        $repositories[] = SalesFunnelsRepository::class;
        return $repositories;
    }

    public function testPaymentIsNotFromSpecificSalesFunnel(): void
    {
        $paymentRow = $this->createPayment('VARSYMBOL');

        $this->salesFunnelsRepository->add('test1', 'test1', 'test-body');
        $this->salesFunnelsRepository->add('test2', 'test2', 'test-body');
        $this->salesFunnelsRepository->add('test3', 'test3', 'test-body');

        $paymentSelection = $this->paymentsRepository->getTable()
            ->where('payments.id', $paymentRow->id);

        /** @var PaymentIsFromSpecificSalesFunnelCriteria $criteria */
        $criteria = $this->inject(PaymentIsFromSpecificSalesFunnelCriteria::class);


        $values = (object)['selection' => [3, 5, 8]];
        $criteria->addConditions($paymentSelection, [PaymentIsFromSpecificSalesFunnelCriteria::KEY => $values], $paymentRow);

        $this->assertNull($paymentSelection->fetch());
    }

    public function testPaymentIsFromSpecificSalesFunnel(): void
    {
        $paymentRow = $this->createPayment('VARSYMBOL');

        $this->salesFunnelsRepository->add('test1', 'test1', 'test-body');
        $salesFunnelRow = $this->salesFunnelsRepository->add('test2', 'test2', 'test-body');
        $this->salesFunnelsRepository->add('test3', 'test3', 'test-body');

        $this->paymentsRepository->update($paymentRow, ['sales_funnel_id' => $salesFunnelRow->id]);

        $paymentSelection = $this->paymentsRepository->getTable()
            ->where('payments.id', $paymentRow->id);

        /** @var PaymentIsFromSpecificSalesFunnelCriteria $criteria */
        $criteria = $this->inject(PaymentIsFromSpecificSalesFunnelCriteria::class);


        $values = (object)['selection' => [5, $salesFunnelRow->id, 8]];
        $criteria->addConditions($paymentSelection, [PaymentIsFromSpecificSalesFunnelCriteria::KEY => $values], $paymentRow);

        $this->assertNotNull($paymentSelection->fetch());
    }
}
