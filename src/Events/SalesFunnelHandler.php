<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsStatsRepository;
use DeviceDetector\DeviceDetector;
use League\Event\AbstractListener;
use League\Event\EventInterface;

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

        if ($event->getUserAgent() !== null) {
            $deviceDetector = new DeviceDetector($event->getUserAgent());
            $deviceDetector->parse();

            if ($deviceDetector->isBot()) {
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
        $this->salesFunnelsStatsRepository->add($salesFunnel, $event->getType(), $event->getDeviceType());
    }
}
