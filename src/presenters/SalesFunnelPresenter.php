<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Nette\Application\BadRequestException;

class SalesFunnelPresenter extends FrontendPresenter
{
    /** @var  PaymentsRepository  @inject */
    public $paymentsRepository;

    /** @var PaymentLogsRepository  @inject */
    public $paymentLogsRepository;

    /** @var SalesFunnelsRepository @inject */
    public $salesFunnelsRepository;

    /** @persistent */
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
        if (!isset($this->variableSymbol)) {
            $this->paymentLogsRepository->add('ERROR', 'No VS provided in GET', $this->request->getUrl());
            $this->redirect('SalesFunnel:Error');
        }

        $payment = $this->paymentsRepository->findByVs($this->variableSymbol);
        if (!$payment) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Cannot find payment with VS='{$this->variableSymbol}'",
                $this->request->getUrl()
            );
            $this->redirect('SalesFunnel:Error');
        }
        if (!in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID])) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment is not paid '{$this->variableSymbol}'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->redirect('SalesFunnel:Error');
        }

        $this->template->payment = $payment;
        $this->template->subscription = $payment->subscription;

        // removing session created in SalesFunnelFrontendPresenter
        $this->getSession('sales_funnel')->remove();
    }

    public function getPayment()
    {
        if (!isset($this->variableSymbol)) {
            return null;
        }
        return $this->paymentsRepository->findByVs($this->variableSymbol);
    }
}
