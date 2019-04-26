<?php

namespace Crm\SalesFunnelModule\Components;

use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;

class WindowPreview extends \Crm\ApplicationModule\Widget\BaseWidget
{
    private $view = 'window_preview';

    private $salesFunnelsRepository;

    private $salesFunnelId;

    public function __construct(WidgetManager $widgetManager, SalesFunnelsRepository $salesFunnelsRepository)
    {
        parent::__construct($widgetManager);
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
