<?php

namespace Crm\SalesFunnelModule;

use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\LayoutManager;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use Crm\SalesFunnelModule\Api\TrackStatsHandler;
use Crm\SalesFunnelModule\DataProvider\PaymentsAdminFilterFormDataProvider;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Seeders\ConfigsSeeder;
use Crm\SalesFunnelModule\Seeders\SalesFunnelsSeeder;
use Kdyby\Translation\Translator;
use League\Event\Emitter;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Symfony\Component\Console\Output\OutputInterface;

class SalesFunnelModule extends CrmModule
{
    private $salesFunnelsCache;

    private $salesFunnelsRepository;

    public function __construct(
        Container $container,
        Translator $translator,
        SalesFunnelsCache $salesFunnelsCache,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct($container, $translator);
        $this->salesFunnelsCache = $salesFunnelsCache;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
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

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\PaymentsModule\Events\PaymentChangeStatusEvent::class,
            $this->getInstance(\Crm\SalesFunnelModule\Events\PaymentStatusChangeHandler::class),
            700
        );
        $emitter->addListener(
            \Crm\SalesFunnelModule\Events\SalesFunnelEvent::class,
            $this->getInstance(\Crm\SalesFunnelModule\Events\SalesFunnelHandler::class)
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
                Api\ListPaymentsPublicMetaHandler::class,
                \Crm\ApiModule\Authorization\NoAuthorization::class
            )
        );
    }

    public function registerRoutes(RouteList $router)
    {
        foreach ($this->salesFunnelsCache->all() as $salesFunnel) {
            $router[] = new Route("<funnel {$salesFunnel->url_key}>", 'SalesFunnel:SalesFunnelFrontend:default');
        }

        $router[] = new Route('/sales-funnel/sales-funnel/<action>[/<variableSymbol>]', 'SalesFunnel:SalesFunnel:success');
    }

    public function cache(OutputInterface $output, array $tags = [])
    {
        if (empty($tags)) {
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

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'subscriptions.new',
            $this->getInstance(\Crm\SalesFunnelModule\Components\NewSubscriptionWidget::class),
            100
        );
        $widgetManager->registerWidget(
            'subscription_types_admin.show.middle',
            $this->getInstance(\Crm\SalesFunnelModule\Components\SubscriptionTypesInSalesFunnelsWidget::class),
            200
        );

        $widgetManager->registerWidget(
            'frontend.payment.success.finish_registration',
            $this->getInstance(\Crm\SalesFunnelModule\Components\FinishRegistrationWidget::class),
            200
        );

        $widgetManager->registerWidget(
            'sales_funnels.admin.show.distribution',
            $this->getInstance(\Crm\SalesFunnelModule\Components\AmountDistributionWidget::class)
        );
        $widgetManager->registerWidget(
            'sales_funnels.admin.show.distribution',
            $this->getInstance(\Crm\SalesFunnelModule\Components\PaymentDistributionWidget::class)
        );
        $widgetManager->registerWidget(
            'sales_funnels.admin.show.distribution',
            $this->getInstance(\Crm\SalesFunnelModule\Components\DaysFromLastSubscriptionDistributionWidget::class)
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_source_listing',
            $this->getInstance(\Crm\SalesFunnelModule\Components\SalesFunnelUserListingWidget::class)
        );
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('sales_funnel', Events\SalesFunnelEvent::class);
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'payments.dataprovider.list_filter_form',
            $this->getInstance(PaymentsAdminFilterFormDataProvider::class)
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(SalesFunnelsSeeder::class));
    }
}
