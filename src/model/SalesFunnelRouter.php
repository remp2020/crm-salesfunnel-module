<?php

namespace Crm\SalesFunnelModule;

use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette\Application\Request as AppRequest;
use Nette\Http\IRequest as HttpRequest;
use Nette\Http\Url;

class SalesFunnelRoute implements IRouter
{
    public function match(HttpRequest $httpRequest)
    {
        $params = false;

        if ($httpRequest->getUrl()->path == '/optout') {
            $params = ['funnel' => '2', 'action' => 'optoutPopup'];
        }

        if ($httpRequest->getUrl()->path == '/zadarmopohoda') {
            $params = ['funnel' => '3', 'action' => 'detail'];
        }

        if ($httpRequest->getUrl()->path == '/bydesign') {
            $params = ['funnel' => '3', 'action' => 'optout99Popup'];
        }

        if ($params) {
            return new Request(
                'SalesFunnel:SalesFunnel',
                $httpRequest->getMethod(),
                $params,
                $httpRequest->getPost(),
                $httpRequest->getFiles(),
                [Request::SECURED => $httpRequest->isSecured()]
            );
        }

        return null;
    }

    public function constructUrl(AppRequest $appRequest, Url $refUrl)
    {
        if ($refUrl->path == '/optout') {
            return "{$refUrl->scheme}://{$refUrl->host}/optout";
        }
        if ($refUrl->path == '/zadarmopohoda') {
            return "{$refUrl->scheme}://{$refUrl->host}/zadarmopohoda";
        }
        return null;
    }
}
