<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

/**
 * Widget that renders page with iframe containing sales funnel.
 * Used in admin to preview sales funnel.
 *
 * @package Crm\SalesFunnelModule\Components
 */
class WindowPreview extends BaseLazyWidget
{
    private $view = 'window_preview';

    private $salesFunnelsRepository;

    private $salesFunnelId;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function setSalesFunnelId($salesFunnelId)
    {
        $this->salesFunnelId = $salesFunnelId;
        return $this;
    }

    public function render()
    {
        if (!$this->salesFunnelId) {
            throw new \Exception("salesFunnelId was not set for windowPreview control");
        }

        $salesFunnel = $this->salesFunnelsRepository->find($this->salesFunnelId);
        if (!$salesFunnel) {
            throw new \Exception("salesFunnel [{$this->salesFunnelId}] was not found");
        }

        $this->template->salesFunnel = $salesFunnel;
        $this->template->setFile(__DIR__ . '/' . $this->view . '.latte');
        $this->template->render();
    }
}
