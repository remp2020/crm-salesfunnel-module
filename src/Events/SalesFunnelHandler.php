<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsStatsRepository;
use Crm\UsersModule\Models\DeviceDetector;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class SalesFunnelHandler extends AbstractListener
{
    public function __construct(
        private SalesFunnelsRepository $salesFunnelsRepository,
        private SalesFunnelsStatsRepository $salesFunnelsStatsRepository,
        private DeviceDetector $deviceDetector,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof SalesFunnelEvent)) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $deviceType = null;
        if ($event->getUserAgent() !== null) {
            $this->deviceDetector->setUserAgent($event->getUserAgent());
            $this->deviceDetector->parse();

            if ($this->deviceDetector->isTablet()) {
                $deviceType = 'tablet';
            } elseif ($this->deviceDetector->isMobile()) {
                $deviceType = 'mobile';
            } else {
                $deviceType = 'desktop';
            }

            if ($this->deviceDetector->isBot()) {
                 // Do not track bot visits
                return;
            }
        }

        $salesFunnel = $event->getSalesFunnel();

        if ($event->getType() === SalesFunnelsStatsRepository::TYPE_SHOW) {
            $this->salesFunnelsRepository->incrementShows($salesFunnel);

            if ($event->getEmail()) {
                $this->salesFunnelsRepository->incrementLoggedInShows($salesFunnel);
            } else {
                $this->salesFunnelsRepository->incrementNotLoggedInShows($salesFunnel);
            }
        }
        if ($event->getType() === SalesFunnelsStatsRepository::TYPE_OK) {
            $this->salesFunnelsRepository->incrementConversions($salesFunnel);
        }
        if ($event->getType() === SalesFunnelsStatsRepository::TYPE_ERROR) {
            $this->salesFunnelsRepository->incrementErrors($salesFunnel);
        }
        $this->salesFunnelsStatsRepository->add($salesFunnel, $event->getType(), $deviceType);
    }
}
