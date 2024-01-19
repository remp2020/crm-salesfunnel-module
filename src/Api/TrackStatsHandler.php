<?php

namespace Crm\SalesFunnelModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Params\InputParam;
use Crm\ApiModule\Models\Params\ParamsProcessor;
use Crm\ApplicationModule\Models\Request;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use League\Event\Emitter;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class TrackStatsHandler extends ApiHandler
{
    private $salesFunnelsRepository;

    private $emitter;

    public function __construct(SalesFunnelsRepository $salesFunnelsRepository, Emitter $emitter)
    {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->emitter = $emitter;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'url_key', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'type', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'user_agent', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $funnel = $this->salesFunnelsRepository->findByUrlKey($params['url_key']);
        if (!$funnel) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => "Sales funnel [{$params['url_key']}] doesn't exist",
            ]);
            return $response;
        }

        $ua = $params['user_agent'] ?? Request::getUserAgent();

        $this->emitter->emit(new SalesFunnelEvent($funnel, null, $params['type'], $ua));

        $result = [
            'status' => 'ok',
            'result' => true,
        ];

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
