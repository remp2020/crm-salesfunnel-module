<?php
declare(strict_types=1);

namespace Crm\SalesFunnelModule\Tests;

use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SalesFunnelModule\Repositories\SalesFunnelTagsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Scenarios\PaymentIsFromTagSalesFunnelCriteria;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

class PaymentIsFromTagSalesFunnelCriteriaTest extends PaymentsTestCase
{
    private SalesFunnelsRepository $salesFunnelsRepository;

    private SalesFunnelTagsRepository $salesFunnelTagsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->salesFunnelsRepository = $this->getRepository(SalesFunnelsRepository::class);
        $this->salesFunnelTagsRepository = $this->getRepository(SalesFunnelTagsRepository::class);
    }

    public function requiredRepositories(): array
    {
        $repositories = parent::requiredRepositories();
        $repositories[] = SalesFunnelsRepository::class;
        $repositories[] = SalesFunnelTagsRepository::class;
        return $repositories;
    }

    public static function dataProviderForTestPaymentIsFromTagSalesFunnelCriteria(): array
    {
        return [
            'MatchingTag_ShouldReturnTrue' => [
                'funnelTags' => ['crowdfunding', 'trial'],
                'testedTags' => [
                    ['crowdfunding', 'premium'],
                ],
                'expectedResult' => true,
            ],
            'DifferentTag_ShouldReturnFalse' => [
                'funnelTags' => ['crowdfunding', 'trial'],
                'testedTags' => [
                    ['premium'],
                ],
                'expectedResult' => false,
            ],
            'EmptyTestedTags_ShouldThrowException' => [
                'funnelTags' => ['crowdfunding', 'trial'],
                'testedTags' => [
                    [],
                ],
                'expectedResult' => null,
            ],
            'ChainedMatchingTags_ShouldReturnTrue' => [
                'funnelTags' => ['crowdfunding', 'trial'],
                'testedTags' => [
                    ['crowdfunding', 'premium'],
                    ['trial'],
                ],
                'expectedResult' => true,
            ],
            'ChainedPartiallyDifferentTags_ShouldReturnFalse' => [
                'funnelTags' => ['crowdfunding', 'trial'],
                'testedTags' => [
                    ['premium'],
                    ['trial'],
                ],
                'expectedResult' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderForTestPaymentIsFromTagSalesFunnelCriteria')]
    public function testPaymentIsFromTagSalesFunnelCriteria(
        array $funnelTags,
        array $testedTags,
        ?bool $expectedResult,
    ): void {
        [$paymentSelection, $paymentRow] = $this->prepareData($funnelTags);

        /** @var PaymentIsFromTagSalesFunnelCriteria $criteria */
        $criteria = $this->inject(PaymentIsFromTagSalesFunnelCriteria::class);

        if ($expectedResult === null) {
            $this->expectException(Exception::class);
        }

        foreach ($testedTags as $tags) {
            $values = (object)['selection' => $tags];
            $criteria->addConditions(
                selection: $paymentSelection,
                paramValues: [PaymentIsFromTagSalesFunnelCriteria::KEY => $values],
                criterionItemRow: $paymentRow,
            );
        }

        if ($expectedResult === true) {
            $this->assertNotNull($paymentSelection->fetch());
        } elseif ($expectedResult === false) {
            $this->assertNull($paymentSelection->fetch());
        }
    }

    public function testPaymentWithoutSalesFunnelShouldReturnFalse(): void
    {
        $paymentRow = $this->createPayment('VARSYMBOL');

        $paymentSelection = $this->paymentsRepository->getTable()
            ->where('payments.id', $paymentRow->id);

        /** @var PaymentIsFromTagSalesFunnelCriteria $criteria */
        $criteria = $this->inject(PaymentIsFromTagSalesFunnelCriteria::class);

        $values = (object)['selection' => ['crowdfunding']];
        $criteria->addConditions(
            selection: $paymentSelection,
            paramValues: [PaymentIsFromTagSalesFunnelCriteria::KEY => $values],
            criterionItemRow: $paymentRow,
        );

        $this->assertNull($paymentSelection->fetch());
    }

    private function prepareData(array $funnelTags): array
    {
        $salesFunnelRow = $this->salesFunnelsRepository->add('Test funnel', 'test_funnel', '');
        $this->salesFunnelTagsRepository->setTagsForSalesFunnel($salesFunnelRow, $funnelTags);

        $paymentRow = $this->createPayment('VARSYMBOL');
        $this->paymentsRepository->update($paymentRow, ['sales_funnel_id' => $salesFunnelRow->id]);

        $paymentSelection = $this->paymentsRepository->getTable()
            ->where('payments.id', $paymentRow->id);

        return [$paymentSelection, $paymentRow];
    }
}
