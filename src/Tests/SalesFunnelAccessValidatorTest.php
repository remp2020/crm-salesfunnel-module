<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Tests;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelAccessDeniedException;
use Crm\SalesFunnelModule\DataProviders\ValidateUserFunnelAccessDataProviderInterface;
use Crm\SalesFunnelModule\Models\SalesFunnelAccessDenial;
use Crm\SalesFunnelModule\Models\SalesFunnelAccessValidator;
use Crm\SalesFunnelModule\Models\SalesFunnelRenderer;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsSubscriptionTypesRepository;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SalesFunnelAccessValidatorTest extends PaymentsTestCase
{
    private SalesFunnelAccessValidator $salesFunnelAccessValidator;
    private SalesFunnelsRepository $salesFunnelsRepository;

    public function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            SalesFunnelsRepository::class,
            SalesFunnelsMetaRepository::class,
            SalesFunnelsSubscriptionTypesRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->salesFunnelAccessValidator = $this->inject(SalesFunnelAccessValidator::class);
        $this->salesFunnelsRepository = $this->getRepository(SalesFunnelsRepository::class);
    }

    public function testAllowed(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel');

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertTrue($result->isAllowed());
        $this->assertNull($result->denial);
        $this->assertSame($salesFunnel->id, $result->targetFunnel->id);
    }

    public function testInactiveFunnel(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', isActive: false);

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_INACTIVE, $result->denial?->reason);
    }

    public function testEndedFunnel(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', endAt: new DateTime('-1 day'));

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_INACTIVE, $result->denial?->reason);
    }

    public function testNotStartedFunnel(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', startAt: new DateTime('+1 day'));

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_INACTIVE, $result->denial?->reason);
    }

    public function testOnlyNotLoggedFunnel(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', onlyNotLogged: true);

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_ONLY_NOT_LOGGED, $result->denial?->reason);
    }

    public function testRedirectFunnelEvaluatesTargetFunnel(): void
    {
        $targetFunnel = $this->createSalesFunnel('target_funnel', isActive: false);
        $redirectFunnel = $this->createSalesFunnel('redirect_funnel', redirectFunnelId: $targetFunnel->id);

        $result = $this->salesFunnelAccessValidator->evaluate($redirectFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_INACTIVE, $result->denial?->reason);
        $this->assertSame($targetFunnel->id, $result->targetFunnel->id);
    }

    public function testRedirectLoop(): void
    {
        $firstFunnel = $this->createSalesFunnel('first_funnel');
        $secondFunnel = $this->createSalesFunnel('second_funnel', redirectFunnelId: $firstFunnel->id);
        $this->salesFunnelsRepository->update($firstFunnel, ['redirect_funnel_id' => $secondFunnel->id]);

        $result = $this->salesFunnelAccessValidator->evaluate($firstFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_REDIRECT_LOOP, $result->denial?->reason);
    }

    public function testPurchaseLimitReached(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel');
        $this->getRepository(SalesFunnelsMetaRepository::class)->add($salesFunnel, 'funnel_purchase_limit', 1);
        $this->createPaidPayment($salesFunnel);

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_PURCHASE_LIMIT, $result->denial?->reason);
    }

    public function testLimitPerUserReached(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', limitPerUser: 1);

        $this->assertTrue($this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser())->isAllowed());

        $this->createPaidPayment($salesFunnel);

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());
        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_LIMIT_PER_USER, $result->denial?->reason);
    }

    public function testLimitPerUserForPeriodIgnoresOlderPurchases(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', limitPerUser: 1, limitPerUserForPeriod: 30);
        $this->createPaidPayment($salesFunnel, new DateTime('-60 days'));

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertTrue($result->isAllowed());
    }

    public function testDataProviderDeniesAccess(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel');
        $validator = $this->createValidatorWithDataProvider(new class implements ValidateUserFunnelAccessDataProviderInterface {
            public function provide(array $params): bool
            {
                return false;
            }
        });

        $result = $validator->evaluate($salesFunnel, $this->getUser());
        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_ACCESS_DENIED, $result->denial?->reason);

        $denial = $validator->validateDataProviders($salesFunnel, $this->getUser());
        $this->assertNotNull($denial);
        $this->assertNull($denial->redirectUrl);
    }

    public function testDataProviderDeniesAccessWithRedirectUrl(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel');
        $validator = $this->createValidatorWithDataProvider(new class implements ValidateUserFunnelAccessDataProviderInterface {
            public function provide(array $params): bool
            {
                throw new SalesFunnelAccessDeniedException('https://example.com/no-access');
            }
        });

        $result = $validator->evaluate($salesFunnel, $this->getUser());
        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_ACCESS_DENIED, $result->denial?->reason);
        $this->assertSame('https://example.com/no-access', $result->denial->redirectUrl);

        $denial = $validator->validateDataProviders($salesFunnel, $this->getUser());
        $this->assertSame('https://example.com/no-access', $denial?->redirectUrl);
    }

    public function testNoPurchasableSubscriptionTypes(): void
    {
        $salesFunnel = $this->createSalesFunnel('test_funnel', withSubscriptionType: false);

        $result = $this->salesFunnelAccessValidator->evaluate($salesFunnel, $this->getUser());

        $this->assertFalse($result->isAllowed());
        $this->assertSame(SalesFunnelAccessDenial::REASON_NO_SUBSCRIPTION_TYPES, $result->denial?->reason);
    }

    private function createSalesFunnel(
        string $urlKey,
        bool $isActive = true,
        ?DateTime $startAt = null,
        ?DateTime $endAt = null,
        bool $onlyNotLogged = false,
        ?int $redirectFunnelId = null,
        ?int $limitPerUser = null,
        ?int $limitPerUserForPeriod = null,
        bool $withSubscriptionType = true,
    ): ActiveRow {
        $salesFunnel = $this->salesFunnelsRepository->add(
            name: $urlKey,
            urlKey: $urlKey,
            body: 'body',
            startAt: $startAt,
            endAt: $endAt,
            isActive: $isActive,
            onlyNotLogged: $onlyNotLogged,
            redirectFunnelId: $redirectFunnelId,
            limitPerUser: $limitPerUser,
            limitPerUserForPeriod: $limitPerUserForPeriod,
        );

        if ($withSubscriptionType) {
            $this->getRepository(SalesFunnelsSubscriptionTypesRepository::class)
                ->add($salesFunnel, $this->getSubscriptionType());
        }

        return $salesFunnel;
    }

    private function createPaidPayment(ActiveRow $salesFunnel, ?DateTime $paidAt = null): void
    {
        $payment = $this->createPayment('1234567890');
        $this->paymentsRepository->update($payment, [
            'sales_funnel_id' => $salesFunnel->id,
            'status' => PaymentStatusEnum::Paid->value,
            'paid_at' => $paidAt ?? new DateTime(),
        ]);
    }

    private function createValidatorWithDataProvider(ValidateUserFunnelAccessDataProviderInterface $provider): SalesFunnelAccessValidator
    {
        // separate manager, so the provider doesn't leak to other tests through the shared container
        $dataProviderManager = new DataProviderManager();
        $dataProviderManager->registerDataProvider('salesfunnel.dataprovider.validate_funnel', $provider);

        return new SalesFunnelAccessValidator(
            $this->salesFunnelsRepository,
            $this->getRepository(SalesFunnelsMetaRepository::class),
            $this->inject(SegmentFactoryInterface::class),
            $dataProviderManager,
            $this->inject(SalesFunnelRenderer::class),
        );
    }
}
