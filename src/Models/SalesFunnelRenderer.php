<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Models;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Twig\Extensions\ContributteTranslationExtension;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelVariablesDataProviderInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SubscriptionsModule\Models\Subscription\ActualUserSubscription;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;

class SalesFunnelRenderer
{
    public function __construct(
        private readonly SalesFunnelsRepository $salesFunnelsRepository,
        private readonly SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly ContentAccessRepository $contentAccessRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly ActualUserSubscription $actualUserSubscription,
        private readonly DataProviderManager $dataProviderManager,
        private readonly ApplicationConfig $applicationConfig,
        private readonly Translator $translator,
        private readonly SandboxExtension $sandboxExtension,
    ) {
    }

    /**
     * @return array<string, ActiveRow>
     */
    public function getPurchasableSubscriptionTypes(ActiveRow $salesFunnel, ?ActiveRow $user = null): array
    {
        $subscriptionTypes = [];
        foreach ($this->salesFunnelsRepository->getSalesFunnelSubscriptionTypes($salesFunnel) as $subscriptionTypesRow) {
            $subscriptionTypes[$subscriptionTypesRow->code] = $subscriptionTypesRow;
        }

        if ($user !== null) {
            $subscriptionTypes = $this->filterSubscriptionTypes($subscriptionTypes, $user->id);
        }

        return $subscriptionTypes;
    }

    /**
     * @param array<string, ActiveRow> $subscriptionTypes
     */
    public function render(
        ActiveRow $salesFunnel,
        array $subscriptionTypes,
        ?ActiveRow $user = null,
        ?string $referer = null,
        ?array $values = null,
        ?array $errors = null,
    ): string {
        $isLoggedIn = $user !== null;

        $headEnd = $this->applicationConfig->get('header_block') . "\n\n"
            . $this->applicationConfig->get('sales_funnel_header_block') . "\n\n"
            . $salesFunnel->head_script;

        $twig = new Environment(new ArrayLoader([
            'funnel_template' => $salesFunnel->body,
            'head_template' => $headEnd,
        ]));
        $twig->addExtension(new ContributteTranslationExtension($this->translator));
        $twig->addExtension($this->sandboxExtension);

        $contentAccess = [];
        foreach ($subscriptionTypes as $code => $subscriptionType) {
            $contentAccess[$code] = $this->contentAccessRepository->pairsForSubscriptionType(
                subscriptionType: $subscriptionType,
                key: 'name',
                value: 'name',
            );

            // casting to array for backwards compatibility and easier Twig access
            $subscriptionTypes[$code] = $subscriptionType->toArray();
        }

        $params = [
            'headEnd' => $headEnd,
            'funnel' => $salesFunnel,
            'isLogged' => $isLoggedIn,
            'gateways' => $this->getGateways($salesFunnel),
            'subscriptionTypes' => $subscriptionTypes,
            'contentAccess' => $contentAccess,
            'addresses' => $isLoggedIn ? $this->addressesRepository->addresses($user, 'print') : [],
            'meta' => $this->salesFunnelsMetaRepository->all($salesFunnel),
            'actualUserSubscription' => $this->actualUserSubscription,
            'referer' => $referer !== null ? urlencode($referer) : null,
            'values' => $values,
            'errors' => $errors,
            'locale' => $this->translator->getLocale(),
        ];

        /** @var SalesFunnelVariablesDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            path: 'sales_funnel.dataprovider.twig_variables',
            validateInterface: SalesFunnelVariablesDataProviderInterface::class,
        );
        foreach ($providers as $provider) {
            foreach ($provider->provide([
                SalesFunnelVariablesDataProviderInterface::PARAM_SALES_FUNNEL => $salesFunnel,
            ]) as $name => $value) {
                $params[$name] = $value;
            }
        }

        if ($isLoggedIn) {
            $params['email'] = $user->email;
            $params['user_id'] = $user->id;
        }

        $params['headEnd'] = $twig->render('head_template', $params);

        return $twig->render('funnel_template', $params);
    }

    private function getGateways(ActiveRow $salesFunnel): array
    {
        $gateways = [];
        foreach ($this->salesFunnelsRepository->getSalesFunnelGateways($salesFunnel) as $gatewayRow) {
            $gateways[$gatewayRow->code] = $gatewayRow->toArray();
        }

        return $gateways;
    }

    private function filterSubscriptionTypes(array $subscriptionTypes, int $userId): array
    {
        $userSubscriptionsTypesCount = $this->subscriptionsRepository
            ->userSubscriptionTypesCounts($userId, array_column($subscriptionTypes, 'id'));

        foreach ($subscriptionTypes as $code => $subscriptionType) {
            if (!isset($userSubscriptionsTypesCount[$subscriptionType['id']])) {
                continue;
            }

            if ($subscriptionType['limit_per_user'] !== null
                && $subscriptionType['limit_per_user'] <= $userSubscriptionsTypesCount[$subscriptionType['id']]
            ) {
                unset($subscriptionTypes[$code]);
            }
        }

        return $subscriptionTypes;
    }
}
