<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentAwareInterface;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;

class SalesFunnelPresenter extends FrontendPresenter implements PaymentAwareInterface
{
    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Inject]
    public PaymentLogsRepository $paymentLogsRepository;

    #[Inject]
    public SalesFunnelsRepository $salesFunnelsRepository;

    #[Persistent]
    public $variableSymbol;

    public function renderNewPopup()
    {
        $urlKey = $this->applicationConfig->get('default_sales_funnel_url_key');
        $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($urlKey);
        if (!$salesFunnel) {
            throw new BadRequestException('invalid sales funnel urlKey: ' . $urlKey);
        }
        $this->redirect(':SalesFunnel:SalesFunnelFrontend:show', ['funnel' => $salesFunnel->url_key, 'referer' => $this->getReferer()]);
    }

    public function renderError()
    {
        $this->template->message = $this->translator->translate('sales_funnel.frontend.error_page.reason_default');
    }

    public function renderCancel($variableSymbol)
    {
        if (!$variableSymbol) {
            $this->redirect('error');
        }
        $payment = $this->paymentsRepository->findByVs($variableSymbol);
        if (!$payment) {
            $this->redirect('error');
        }
        $funnel = $this->salesFunnelsRepository->find($payment->sales_funnel_id);
        if (!$funnel) {
            throw new BadRequestException('invalid sales funnel id provided: ' . $payment->sales_funnel_id);
        }
        $this->template->funnel = $funnel;
        $this->template->payment = $payment;
    }

    public function renderSuccess()
    {
        $user = $this->getUser();
        if (!$user->isLoggedIn()) {
            throw new BadRequestException('User is not logged in', httpCode: IResponse::S404_NotFound);
        }

        if (!isset($this->variableSymbol)) {
            $this->paymentLogsRepository->add('ERROR', 'No VS provided in GET', $this->request->getUrl());
            $this->redirect('SalesFunnel:Error');
        }

        $payment = $this->paymentsRepository->findByVs($this->variableSymbol);
        if (!$payment) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Cannot find payment with VS='{$this->variableSymbol}'",
                $this->request->getUrl(),
            );
            $this->redirect('SalesFunnel:Error');
        }

        if ($user->getId() !== $payment->user_id) {
            throw new BadRequestException("User hasn't access to the payment.", httpCode: IResponse::S404_NotFound);
        }

        if (!in_array($payment->status, [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value, PaymentStatusEnum::Authorized->value], true)) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment is not paid '{$this->variableSymbol}'",
                $this->request->getUrl(),
                $payment->id,
            );
            $this->redirect('SalesFunnel:Error');
        }

        $this->template->payment = $payment;
        $this->template->subscription = $payment->subscription;

        // removing session created in SalesFunnelFrontendPresenter
        $this->getSession('sales_funnel')->remove();
    }

    public function getPayment(): ?ActiveRow
    {
        if (!isset($this->variableSymbol)) {
            return null;
        }
        return $this->paymentsRepository->findByVs($this->variableSymbol);
    }
}
