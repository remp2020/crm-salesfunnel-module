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
        $message = $this->translator->translate('sales_funnel.frontend.error_page.reason_default');
        $found = false;
        if (isset($this->params['subscription_type_id'])) {
            if (in_array($this->params['subscription_type_id'], [1, 2, 3, 4, 5, 6, 7, 8])) {
                $params = ['subscription_type_id' => $this->params['subscription_type_id']];
                if (isset($this->params['payment_gateway_id'])) {
                    $params['payment_gateway_id'] = $this->params['payment_gateway_id'];
                }
                $link = $this->link(':Subscriptions:Subscriptions:new', $params);
                $message = 'Prosím, <a href="' . $link . '" style="text-decoration:underline">skúste ju zopakovať ešte raz</a> alebo kontaktujte našu technickú podporu.';
                $found = true;
            }
        }

        if (!$found && isset($this->params['subscription_type'])) {
            if (in_array($this->params['subscription_type'], ['month_optout', 'month_club_optout'])) {
                $link = $this->link('optout1Popup', ['subscription_type' => $this->params['subscription_type']]);
                $message = 'Prosím, <a href="' . $link . '" style="text-decoration:underline">skúste ju zopakovať ešte raz</a> alebo kontaktujte našu technickú podporu.';
                $found = true;
            }
            if (in_array($this->params['subscription_type'], ['2_months_web_99c', '2_months_web_club_99c'])) {
                $link = $this->link('optout99Popup', ['subscription_type' => $this->params['subscription_type']]);
                $message = 'Prosím, <a href="' . $link . '" style="text-decoration:underline">skúste ju zopakovať ešte raz</a> alebo kontaktujte našu technickú podporu.';
                $found = true;
            }
            if (in_array($this->params['subscription_type'], ['hbo_web', 'hbo_app', 'hbo_print'])) {
                $params = ['subscription_type' => $this->params['subscription_type']];
                if (isset($this->params['payment_gateway_id'])) {
                    $params['payment_gateway_id'] = $this->params['payment_gateway_id'];
                }
                $link = $this->link('funnelHbo', $params);
                $message = 'Prosím, <a href="' . $link . '" style="text-decoration:underline">skúste ju zopakovať ešte raz</a> alebo kontaktujte našu technickú podporu.';
                $found = true;
            }
        }

        $this->template->message = $message;
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
        $this->template->destination = $this->paymentMetaRepository->values($payment, 'destination')->fetch()->value;

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
