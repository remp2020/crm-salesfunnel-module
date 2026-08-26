<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Tests;

use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SalesFunnelModule\Models\SalesFunnelRenderer;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsSubscriptionTypesRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

class SalesFunnelRendererTest extends PaymentsTestCase
{
    private SalesFunnelRenderer $salesFunnelRenderer;
    private SalesFunnelsRepository $salesFunnelsRepository;

    public function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            SalesFunnelsRepository::class,
            SalesFunnelsSubscriptionTypesRepository::class,
            ContentAccessRepository::class,
            SubscriptionTypeContentAccessRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->salesFunnelRenderer = $this->inject(SalesFunnelRenderer::class);
        $this->salesFunnelsRepository = $this->getRepository(SalesFunnelsRepository::class);
    }

    public function testRenderRendersStoredBodyWithTwigVariables(): void
    {
        $salesFunnel = $this->salesFunnelsRepository
            ->add('test funnel', 'test_funnel', '<h1>{{ funnel.url_key }}</h1>');

        $html = $this->salesFunnelRenderer->render($salesFunnel, []);

        $this->assertStringContainsString('<h1>test_funnel</h1>', $html);
    }

    public function testRenderPassesSubscriptionTypesWithContentAccessToTemplate(): void
    {
        $salesFunnel = $this->salesFunnelsRepository->add(
            'test funnel',
            'test_funnel',
            '{% for type in subscriptionTypes %}{{ type.code }}:{{ contentAccess[type.code]|join(",") }}{% endfor %}',
        );
        $subscriptionType = $this->getSubscriptionType();
        $this->getRepository(SalesFunnelsSubscriptionTypesRepository::class)->add($salesFunnel, $subscriptionType);
        $this->getRepository(ContentAccessRepository::class)->addAccess($subscriptionType, 'web');

        $subscriptionTypes = $this->salesFunnelRenderer->getPurchasableSubscriptionTypes($salesFunnel);
        $html = $this->salesFunnelRenderer->render($salesFunnel, $subscriptionTypes);

        $this->assertStringContainsString('my_subscription_type:web', $html);
    }

    public function testGetPurchasableSubscriptionTypesFiltersTypesOverPerUserLimit(): void
    {
        $salesFunnel = $this->salesFunnelsRepository->add('test funnel', 'test_funnel', 'body');

        $unlimitedType = $this->getSubscriptionType();
        $limitedType = $this->inject(SubscriptionTypeBuilder::class)->createNew()
            ->setNameAndUserLabel('limited subscription type')
            ->setPrice(12.2)
            ->setCode('limited_subscription_type')
            ->setLength(31)
            ->setActive(true)
            ->setLimitPerUser(1)
            ->save();

        $salesFunnelsSubscriptionTypesRepository = $this->getRepository(SalesFunnelsSubscriptionTypesRepository::class);
        $salesFunnelsSubscriptionTypesRepository->add($salesFunnel, $unlimitedType);
        $salesFunnelsSubscriptionTypesRepository->add($salesFunnel, $limitedType);

        $user = $this->getUser();
        $this->getRepository(SubscriptionsRepository::class)->add(
            subscriptionType: $limitedType,
            isRecurrent: false,
            isPaid: true,
            user: $user,
            sendEmail: false,
        );

        $allTypes = $this->salesFunnelRenderer->getPurchasableSubscriptionTypes($salesFunnel);
        $this->assertEqualsCanonicalizing(
            ['my_subscription_type', 'limited_subscription_type'],
            array_keys($allTypes),
        );

        $filteredTypes = $this->salesFunnelRenderer->getPurchasableSubscriptionTypes($salesFunnel, $user);
        $this->assertEqualsCanonicalizing(['my_subscription_type'], array_keys($filteredTypes));
    }
}
