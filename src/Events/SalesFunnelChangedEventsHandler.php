<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\SalesFunnelModule\DI\Config;
use Crm\SalesFunnelModule\SalesFunnelsCache;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class SalesFunnelChangedEventsHandler extends AbstractListener
{
    private Config $config;
    private SalesFunnelsCache $salesFunnelsCache;

    public function __construct(
        Config $config,
        SalesFunnelsCache $salesFunnelsCache
    ) {
        $this->config = $config;
        $this->salesFunnelsCache = $salesFunnelsCache;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof SalesFunnelCreatedEvent || $event instanceof SalesFunnelUpdatedEvent)) {
            throw new \Exception('Invalid type of event received, SalesFunnelCreatedEvent or SalesFunnelUpdatedEvent expected, but got: ' . get_class($event));
        }

        // update cached funnel URLs if caching is enabled
        // (see `sales_funnel.funnel_routes` -> https://github.com/remp2020/crm-salesfunnel-module/blob/23bcaf6df124b879004e94121e52f2e026908f53/README.md)
        if ($this->config->getFunnelRoutes()) {
            $salesFunnel = $event->getSalesFunnel();

            // remove old URL if it changed
            if ($event instanceof SalesFunnelUpdatedEvent) {
                $oldSalesFunnel = $event->getOldSalesFunnel();

                if ($salesFunnel->url_key !== $oldSalesFunnel->url_key) {
                    $this->salesFunnelsCache->remove($oldSalesFunnel->id);
                }
            }
            $this->salesFunnelsCache->add($salesFunnel->id, $salesFunnel->url_key);
        }
    }
}
