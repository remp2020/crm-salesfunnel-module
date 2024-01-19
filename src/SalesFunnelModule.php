<?php

namespace Crm\SalesFunnelModule;

use Contributte\Translation\Translator;
use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\Application\CommandsContainerInterface;
use Crm\ApplicationModule\Application\Managers\LayoutManager;
use Crm\ApplicationModule\Application\Managers\SeederManager;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Models\Menu\MenuItem;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManagerInterface;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\SalesFunnelModule\Api\ListPaymentsPublicMetaHandler;
use Crm\SalesFunnelModule\Api\TrackStatsHandler;
use Crm\SalesFunnelModule\Commands\CalculateSalesFunnelsConversionDistributionsCommand;
use Crm\SalesFunnelModule\Components\AmountDistributionWidget\AmountDistributionWidget;
use Crm\SalesFunnelModule\Components\DaysFromLastSubscriptionDistributionWidget\DaysFromLastSubscriptionDistributionWidget;
use Crm\SalesFunnelModule\Components\FinishRegistrationWidget\FinishRegistrationWidget;
use Crm\SalesFunnelModule\Components\NewSubscriptionWidget\NewSubscriptionWidget;
use Crm\SalesFunnelModule\Components\PaymentDistributionWidget\PaymentDistributionWidget;
use Crm\SalesFunnelModule\Components\SalesFunnelUserListingWidget\SalesFunnelUserListingWidget;
use Crm\SalesFunnelModule\Components\SubscriptionTypesInSalesFunnelsWidget\SubscriptionTypesInSalesFunnelsWidget;
use Crm\SalesFunnelModule\DI\Config;
use Crm\SalesFunnelModule\DataProviders\PaymentsAdminFilterFormDataProvider;
use Crm\SalesFunnelModule\DataProviders\RetentionAnalysisDataProvider;
use Crm\SalesFunnelModule\Events\CalculateSalesFunnelConversionDistributionEvent;
use Crm\SalesFunnelModule\Events\CalculateSalesFunnelConversionDistributionEventHandler;
use Crm\SalesFunnelModule\Events\PaymentStatusChangeHandler;
use Crm\SalesFunnelModule\Events\SalesFunnelChangedEventsHandler;
use Crm\SalesFunnelModule\Events\SalesFunnelCreatedEvent;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Events\SalesFunnelHandler;
use Crm\SalesFunnelModule\Events\SalesFunnelUpdatedEvent;
use Crm\SalesFunnelModule\Models\SalesFunnelsCache;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Scenarios\PaymentIsFromSalesFunnelCriteria;
use Crm\SalesFunnelModule\Scenarios\PaymentIsFromSpecificSalesFunnelCriteria;
use Crm\SalesFunnelModule\Seeders\ConfigsSeeder;
use Crm\SalesFunnelModule\Seeders\SalesFunnelsSeeder;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Symfony\Component\Console\Output\OutputInterface;

class SalesFunnelModule extends CrmModule
{
    private $salesFunnelsCache;

    private $salesFunnelsRepository;

    private $config;

    public function __construct(
        Container $container,
        Translator $translator,
        SalesFunnelsCache $salesFunnelsCache,
        SalesFunnelsRepository $salesFunnelsRepository,
        Config $config
    ) {
        parent::__construct($container, $translator);
        $this->salesFunnelsCache = $salesFunnelsCache;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->config = $config;
    }

    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            $this->translator->translate('sales_funnel.menu.sales_funnels'),
            ':SalesFunnel:SalesFunnelsAdmin:default',
            'fa fa-globe',
            489
        );

        $menuContainer->attachMenuItem($mainMenu);
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            PaymentChangeStatusEvent::class,
            PaymentStatusChangeHandler::class,
            700
        );
        $emitter->addListener(
            PaymentChangeStatusEvent::class,
            CalculateSalesFunnelConversionDistributionEventHandler::class,
            800
        );
        $emitter->addListener(
            SalesFunnelEvent::class,
            SalesFunnelHandler::class
        );
        $emitter->addListener(
            SalesFunnelCreatedEvent::class,
            SalesFunnelChangedEventsHandler::class
        );
        $emitter->addListener(
            SalesFunnelUpdatedEvent::class,
            SalesFunnelChangedEventsHandler::class
        );

        $emitter->addListener(
            CalculateSalesFunnelConversionDistributionEvent::class,
            CalculateSalesFunnelConversionDistributionEventHandler::class
        );
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'sales-funnel', 'track'),
                TrackStatsHandler::class,
                NoAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'sales-funnel', 'list-payments-public-meta'),
                ListPaymentsPublicMetaHandler::class,
                NoAuthorization::class
            )
        );
    }

    public function registerRoutes(RouteList $router)
    {
        if ($this->config->getFunnelRoutes()) {
            foreach ($this->salesFunnelsCache->all() as $salesFunnel) {
                $router->addRoute("<funnel {$salesFunnel->url_key}>", 'SalesFunnel:SalesFunnelFrontend:default');
            }
        }

        $router->addRoute('/sales-funnel/sales-funnel/<action>[/<variableSymbol>]', 'SalesFunnel:SalesFunnel:success');
    }

    public function cache(OutputInterface $output, array $tags = [])
    {
        if (empty($tags) && $this->config->getFunnelRoutes()) {
            $funnelsCount = $this->salesFunnelsRepository->getTable()->count('*');
            if ($funnelsCount) {
                $this->salesFunnelsCache->removeAll();
                foreach ($this->salesFunnelsRepository->getTable() as $salesFunnel) {
                    $this->salesFunnelsCache->add($salesFunnel->id, $salesFunnel->url_key);
                    $output->writeln("  * adding funnel <info>$salesFunnel->url_key</info>");
                }
            }
        }
    }

    public function registerLayouts(LayoutManager $layoutManager)
    {
        $layoutManager->registerLayout(
            'sales_funnel_plain',
            realpath(__DIR__ . '/templates/@frontend_layout_plain.latte')
        );
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'subscriptions.new',
            NewSubscriptionWidget::class,
            100
        );
        $widgetManager->registerWidget(
            'subscription_types_admin.show.right',
            SubscriptionTypesInSalesFunnelsWidget::class,
            200
        );

        $widgetManager->registerWidget(
            'frontend.payment.success.finish_registration',
            FinishRegistrationWidget::class,
            200
        );

        $widgetManager->registerWidget(
            'sales_funnels.admin.show.distribution',
            AmountDistributionWidget::class
        );
        $widgetManager->registerWidget(
            'sales_funnels.admin.show.distribution',
            PaymentDistributionWidget::class
        );
        $widgetManager->registerWidget(
            'sales_funnels.admin.show.distribution',
            DaysFromLastSubscriptionDistributionWidget::class
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_source_listing',
            SalesFunnelUserListingWidget::class
        );
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('sales_funnel', SalesFunnelEvent::class);
        $eventsStorage->register('sales_funnel_created', SalesFunnelCreatedEvent::class);
        $eventsStorage->register('sales_funnel_updated', SalesFunnelUpdatedEvent::class);
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'payments.dataprovider.payments_filter_form',
            $this->getInstance(PaymentsAdminFilterFormDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            'payments.dataprovider.retention_analysis',
            $this->getInstance(RetentionAnalysisDataProvider::class)
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(SalesFunnelsSeeder::class));
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('payment', PaymentIsFromSalesFunnelCriteria::KEY, $this->getInstance(PaymentIsFromSalesFunnelCriteria::class));
        $scenariosCriteriaStorage->register('payment', PaymentIsFromSpecificSalesFunnelCriteria::KEY, $this->getInstance(PaymentIsFromSpecificSalesFunnelCriteria::class));
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(CalculateSalesFunnelsConversionDistributionsCommand::class));
    }
}
