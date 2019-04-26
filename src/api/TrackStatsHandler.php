<?php

namespace Crm\SalesFunnelModule\Api;

use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Api\ApiHandler;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use League\Event\Emitter;
use Nette\Http\Response;

class TrackStatsHandler extends ApiHandler
{
    private $salesFunnelsRepository;

    private $emitter;

    public function __construct(SalesFunnelsRepository $salesFunnelsRepository, Emitter $emitter)
    {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->emitter = $emitter;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'url_key', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'type', InputParam::REQUIRED),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $funnel = $this->salesFunnelsRepository->findByUrlKey($params['url_key']);
        if (!$funnel) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Sales funnel [{$params['url_key']}] doesn't exist",
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $this->emitter->emit(new SalesFunnelEvent($funnel, null, $params['type']));

        $result = [
            'status' => 'ok',
            'result' => true,
        ];

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
