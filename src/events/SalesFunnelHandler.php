<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsStatsRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\UserAgent;

class SalesFunnelHandler extends AbstractListener
{
    private $salesFunnelsRepository;

    private $salesFunnelsStatsRepository;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        SalesFunnelsStatsRepository $salesFunnelsStatsRepository
    ) {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->salesFunnelsStatsRepository = $salesFunnelsStatsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof SalesFunnelEvent)) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $salesFunnel = $event->getSalesFunnel();

        if ($event->getUserAgent() !== null) {
            $browser = new Browser($event->getUserAgent());
            if ($browser->isRobot()) {
                // Do not track robot visits
                return;
            }
        }

        if ($event->getType() === SalesFunnelsStatsRepository::TYPE_SHOW) {
            $this->salesFunnelsRepository->incrementShows($salesFunnel);
        }
        if ($event->getType() === SalesFunnelsStatsRepository::TYPE_OK) {
            $this->salesFunnelsRepository->incrementConversions($salesFunnel);
        }
        if ($event->getType() === SalesFunnelsStatsRepository::TYPE_ERROR) {
            $this->salesFunnelsRepository->incrementErrors($salesFunnel);
        }
        $this->salesFunnelsStatsRepository->add($salesFunnel, $event->getType(), $event->getDeviceType());
    }
}
