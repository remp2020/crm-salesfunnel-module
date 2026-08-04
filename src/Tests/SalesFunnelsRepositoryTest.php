<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Tests;

use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class SalesFunnelsRepositoryTest extends PaymentsTestCase
{
    private SalesFunnelsRepository $salesFunnelsRepository;

    public function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            SalesFunnelsRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->salesFunnelsRepository = $this->getRepository(SalesFunnelsRepository::class);
    }

    public static function providePaidFromCases(): array
    {
        return [
            'NoPaidFrom_CountsAllPaidPayments' => [
                'paidFromDaysAgo' => null,
                'expectedCount' => 3,
            ],
            'PaidFrom92Days_ExcludesOlderPayments' => [
                'paidFromDaysAgo' => 92,
                'expectedCount' => 2,
            ],
            'PaidFrom30Days_CountsOnlyRecentPayment' => [
                'paidFromDaysAgo' => 30,
                'expectedCount' => 1,
            ],
        ];
    }

    #[DataProvider('providePaidFromCases')]
    public function testGetAllUserSalesFunnelPurchases(?int $paidFromDaysAgo, int $expectedCount): void
    {
        $funnel = $this->salesFunnelsRepository->add('test funnel', 'test_funnel', '');
        $otherFunnel = $this->salesFunnelsRepository->add('other funnel', 'other_funnel', '');

        $this->createPaidFunnelPayment($funnel, DateTime::from('-400 days'), 'vs_oldest');
        $this->createPaidFunnelPayment($funnel, DateTime::from('-50 days'), 'vs_older');
        $this->createPaidFunnelPayment($funnel, DateTime::from('-5 days'), 'vs_recent');
        $this->createPaidFunnelPayment($otherFunnel, DateTime::from('-5 days'), 'vs_other_funnel');

        $unpaidPayment = $this->createPayment('vs_unpaid');
        $this->paymentsRepository->update($unpaidPayment, ['sales_funnel_id' => $funnel->id]);

        $paidFrom = $paidFromDaysAgo !== null ? DateTime::from("-{$paidFromDaysAgo} days") : null;
        $count = $this->salesFunnelsRepository
            ->getAllUserSalesFunnelPurchases($this->getUser()->id, $funnel->id, $paidFrom)
            ->count(':payments.id');

        $this->assertSame($expectedCount, $count);
    }

    private function createPaidFunnelPayment(ActiveRow $funnel, DateTime $paidAt, string $variableSymbol): void
    {
        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, [
            'sales_funnel_id' => $funnel->id,
            'status' => PaymentStatusEnum::Paid->value,
            'paid_at' => $paidAt,
        ]);
    }
}
