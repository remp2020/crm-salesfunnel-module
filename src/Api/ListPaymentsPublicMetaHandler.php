<?php

namespace Crm\SalesFunnelModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ListPaymentsPublicMetaHandler extends ApiHandler
{
    private $paymentsRepository;

    private $salesFunnelsRepository;

    private $salesFunnelsMetaRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        SalesFunnelsRepository $salesFunnelsRepository,
        SalesFunnelsMetaRepository $salesFunnelsMetaRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->salesFunnelsMetaRepository = $salesFunnelsMetaRepository;
    }

    public function params(): array
    {
        return [
            (new PostInputParam('sales_funnel_url_key'))->setRequired(),
            (new PostInputParam('meta_keys'))->setMulti(),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        if (!$params['sales_funnel_url_key']) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => 'No valid sales funnel url key',
                'code' => 'url_key_missing'
            ]);
            return $response;
        }

        $funnel = $this->salesFunnelsRepository->findByUrlKey($params['sales_funnel_url_key']);
        if (!$funnel) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => 'Sales funnel does not exists.',
                'code' => 'not_existing_sales_funnel'
            ]);
            return $response;
        }

        $allowed = $this->salesFunnelsMetaRepository->get($funnel, 'api_allow_public_list_payments');
        if (!$allowed) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, [
                'status' => 'error',
                'message' => 'Sales funnel does not allow listing payments.',
                'code' => 'not_allowed'
            ]);
            return $response;
        }

        $payments = $this->paymentsRepository->findBySalesFunnelUrlKey($params['sales_funnel_url_key'])
            ->where('payments.status = ?', PaymentStatusEnum::Paid->value)
            ->order('payments.created_at ASC');

        $data = [];
        foreach ($payments as $payment) {
            /** @var ActiveRow $payment */
            $item = [
                'amount' => $payment->amount,
                'meta' => [],
            ];

            foreach ($payment->related('payment_meta.payment_id') as $paymentMeta) {
                if ($params['meta_keys'] && in_array($paymentMeta->key, $params['meta_keys'], true)) {
                    $item['meta'][$paymentMeta->key] = $paymentMeta->value;
                }
            }

            $data[] = $item;
        }

        $response = new JsonApiResponse(Response::S200_OK, [
            'status' => 'ok',
            'data' => $data
        ]);

        return $response;
    }
}
