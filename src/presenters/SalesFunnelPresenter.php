<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\RecurrentPaymentsProcessor;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Model\PaymentCompleteRedirectManager;
use Crm\SalesFunnelModule\Model\PaymentCompleteRedirectResolver;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsStatsRepository;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Nette\Utils\DateTime;

class SalesFunnelPresenter extends FrontendPresenter
{
    /** @var  PaymentProcessor @inject */
    public $paymentProcessor;

    /** @var  PaymentsRepository  @inject */
    public $paymentsRepository;

    /** @var PaymentLogsRepository  @inject */
    public $paymentLogsRepository;

    /** @var  AccessToken @inject */
    public $accessToken;

    /** @var  Nette\Http\Response  @inject */
    public $response;

    /** @var  Nette\Http\Request @inject */
    public $request;

    /** @var  Nette\Http\Session @inject */
    public $session;

    /** @var SalesFunnelsRepository @inject */
    public $salesFunnelsRepository;

    /** @var UserMetaRepository @inject */
    public $userMetaRepository;

    /** @var PaymentCompleteRedirectManager @inject */
    public $paymentCompleteRedirectManager;

    /** @var RecurrentPaymentsRepository @inject */
    public $recurrentPaymentsRepository;

    /** @var RecurrentPaymentsProcessor @inject */
    public $recurrentPaymentsProcessor;

    /** @var GatewayFactory @inject */
    public $gatewayFactory;
    
    /** @persistent */
    public $VS;

    /** @var Emitter @inject */
    public $emitter;

    public function startup()
    {
        parent::startup();
        if (isset($this->params['vs'])) {
            $this->VS = $this->params['vs'];
        } elseif (isset($_POST['VS'])) {
            $this->VS = $_POST['VS'];
        } elseif (isset($_GET['VS'])) {
            $this->VS = $_GET['VS'];
        } elseif (isset($_POST['vs'])) {
            $this->VS = $_POST['vs'];
        } elseif (isset($_GET['vs'])) {
            $this->VS = $_GET['vs'];
        }
    }

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

    public function renderCancel()
    {
        $payment = $this->getPayment();
        $funnel = $this->salesFunnelsRepository->find($payment->sales_funnel_id);
        if (!$funnel) {
            throw new BadRequestException('invalid sales funnel id provided: ' . $payment->sales_funnel_id);
        }
        $this->template->funnel = $funnel;
        $this->template->payment = $payment;
    }

    public function renderUpgrade($id = 'mobile')
    {
        $this->template->service = $id;
    }

    public function renderFixUpgrade($id = 'mobile')
    {
        $this->template->service = $id;
    }

    public function renderMonthUpgrade()
    {
    }

    private function returnPayment($gatewayCode)
    {
        $payment = $this->getPayment();
        if (!$payment) {
            $this->redirect('SalesFunnel:Error');
        }
        if ($payment->payment_gateway->code !== $gatewayCode) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Return to wrong payment type '{$gatewayCode}'",
                $this->actualUrl(),
                $payment->id
            );
            $this->redirect('SalesFunnel:Error');
        }
        return $this->processPayment($payment);
    }

    public function renderReturnPayment($gatewayCode)
    {
        return $this->returnPayment($gatewayCode);
    }

    public function renderReturnPaymentPaypal()
    {
        return $this->returnPayment('paypal');
    }

    public function renderReturnPaymentPaypalReference()
    {
        return $this->returnPayment('paypal_reference');
    }

    public function renderReturnPaymentCsob()
    {
        return $this->returnPayment('csob');
    }

    public function renderReturnPaymentCsobOneClick()
    {
        return $this->returnPayment('csob_one_click');
    }

    public function renderReturnPaymentTatraPay()
    {
        return $this->returnPayment('tatrapay');
    }

    public function renderReturnPaymentCardPay()
    {
        return $this->returnPayment('cardpay');
    }

    public function renderReturnPaymentComfortPay()
    {
        return $this->returnPayment('comfortpay');
    }

    public function renderReturnPaymentViamo()
    {
        $responseString = urldecode($this->params['responseString']);
        $parts = explode('*', $responseString);
        $payment = false;
        foreach ($parts as $pairs) {
            list($key, $value) = explode(':', $pairs);
            if ($key == 'VS') {
                $payment = $this->paymentsRepository->findByVs($value);
                break;
            }
        }

        if (!$payment) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment not found (viamo)",
                $this->actualUrl()
            );
            $this->redirect('SalesFunnel:Error');
            return;
        }

        if ($payment->payment_gateway->code != 'viamo') {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Return to wrong payment type 'viamo'",
                $this->actualUrl(),
                $payment->id
            );
            $this->redirect('SalesFunnel:Error');
            return;
        }
        $this->processPayment($payment);
    }

    private function processPayment($payment)
    {
        $presenter = $this;

        $this->paymentProcessor->complete($payment, function ($payment, GatewayAbstract $gateway) use ($presenter) {
            if (in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID])) {
                // confirmed payment == agreed to terms
                if (!$this->userMetaRepository->exists($payment->user, 'gdpr')) {
                    $this->userMetaRepository->setMeta($payment->user, ['gdpr' => 'confirm_payment']);
                }

                // autologin user after the payment (unless he's an admin)
                if (!$this->getUser()->isLoggedIn()) {
                    // autologin regular user with regular payment
                    if ($payment->user->role !== UsersRepository::ROLE_ADMIN) {
                        $presenter->getUser()->login(['username' => $payment->user->email, 'alwaysLogin' => true]);
                    } else {
                        // redirect admin user to sign in form (no autologin allowed)
                        $presenter->flashMessage($this->translator->translate('sales_funnel.frontend.disabled_auto_login.title'), 'warning');
                        $presenter->redirect($this->applicationConfig->get('not_logged_in_route'), ['back' => $this->storeRequest()]);
                    }
                }

                // issue new access token with new access data (old token will be removed)
                if ($presenter->getUser()->isLoggedIn()) {
                    $presenter->accessToken->addUserToken(
                        $presenter->getUser(),
                        $presenter->request,
                        $presenter->response
                    );
                }
                $presenter->paymentLogsRepository->add(
                    'OK',
                    "Redirecting to success url with vs '{$payment->variable_symbol}'",
                    $presenter->actualUrl(),
                    $payment->id
                );

                foreach ($this->paymentCompleteRedirectManager->getResolvers() as $resolver) {
                    if ($resolver->wantsToRedirect($payment, PaymentCompleteRedirectResolver::PAID)) {
                        $presenter->redirect(...$resolver->redirectArgs($payment, PaymentCompleteRedirectResolver::PAID));
                    }
                }

                // default redirect if no resolver decided to take control
                $presenter->redirect('SalesFunnel:Success', ['VS' => $payment->variable_symbol]);
            } elseif ($gateway->isNotSettled()) {
                $presenter->paymentLogsRepository->add(
                    'ERROR',
                    'Payment not settled, should be confirmed later',
                    $presenter->actualUrl(),
                    $payment->id
                );

                foreach ($this->paymentCompleteRedirectManager->getResolvers() as $resolver) {
                    if ($resolver->wantsToRedirect($payment, PaymentCompleteRedirectResolver::NOT_SETTLED)) {
                        $presenter->redirect(...$resolver->redirectArgs($payment, PaymentCompleteRedirectResolver::NOT_SETTLED));
                    }
                }

                // default redirect if no resolver decided to take control
                $presenter->redirect('SalesFunnel:NotSettled');
            } else {
                $presenter->paymentLogsRepository->add(
                    'ERROR',
                    'Complete payment with unpaid payment',
                    $presenter->actualUrl(),
                    $payment->id
                );

                if ($gateway->isCancelled()) {
                    $resolverStatus = PaymentCompleteRedirectResolver::CANCELLED;
                } else {
                    $resolverStatus = PaymentCompleteRedirectResolver::ERROR;
                }
                foreach ($this->paymentCompleteRedirectManager->getResolvers() as $resolver) {
                    if ($resolver->wantsToRedirect($payment, $resolverStatus)) {
                        $presenter->redirect(...$resolver->redirectArgs($payment, $resolverStatus));
                    }
                }

                // default redirect if no resolver decided to take control
                if ($gateway->isCancelled()) {
                    $presenter->redirect('SalesFunnel:Cancel', [
                        'salesFunnelId' => $payment->sales_funnel_id,
                    ]);
                } else {
                    $presenter->redirect('SalesFunnel:Error', [
                        'payment_gateway_id' => $payment->payment_gateway_id,
                        'subscription_type_id' => $payment->subscription_type_id,
                        'subscription_type' => $payment->subscription_type ? $payment->subscription_type->code : null,
                    ]);
                }
            }
        });
    }

    public function handleUseExistingCard(int $recurrentPaymentId, int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $this->checkPaymentBelongsToUser($this->getUser(), $payment);

        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        if (!$recurrentPayment) {
            $funnel = $this->salesFunnelsRepository->find($payment->sales_funnel_id);

            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('error');
        }

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof RecurrentPaymentInterface) {
            throw new \Exception('gateway is not instance of RecurrentPaymentInterface: ' . get_class($gateway));
        }

        $success = $this->recurrentPaymentsProcessor->chargeRecurrentUsingCid($payment, $recurrentPayment->cid, $gateway);

        if ($success) {
            $this->redirect('SalesFunnel:Success', ['VS' => $payment->variable_symbol]);
        }

        $this->redirect('SalesFunnel:Error');
    }

    public function handleUseNewCard(int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $this->checkPaymentBelongsToUser($this->getUser(), $payment);

        try {
            $this->paymentProcessor->begin($payment);
        } catch (CannotProcessPayment $err) {
            $this->redirect('error');
        }
    }

    private function checkPaymentBelongsToUser(User $user, ActiveRow $payment)
    {
        if ($payment->user_id !== $user->getId() || $payment->status !== PaymentsRepository::STATUS_FORM) {
            $this->redirect('error');
        }
    }

    public function renderReturnPaymentBanktransfer()
    {
        $this->template->bankNumber = $this->applicationConfig->get('supplier_bank_account_number');
        $this->template->bankIban = $this->applicationConfig->get('supplier_iban');
        $this->template->bankSwift = $this->applicationConfig->get('supplier_swift');

        $payment = $this->getPayment();
        $this->template->payment = $payment;
        $this->template->note = 'VS' . $payment->variable_symbol;
    }

    public function renderSuccess()
    {
        if (!isset($this->VS)) {
            $this->paymentLogsRepository->add('ERROR', 'No VS provided in GET', $this->actualUrl());
            $this->redirect('SalesFunnel:Error');
        }

        $this->template->contactEmail = $this->applicationConfig->get('contact_email');

        $payment = $this->paymentsRepository->findByVs($this->VS);
        if (!$payment) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Cannot find payment with VS='{$this->VS}'",
                $this->actualUrl()
            );
            $this->redirect('SalesFunnel:Error');
        }
        if (!in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID])) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment is not paid '{$this->VS}'",
                $this->actualUrl(),
                $payment->id
            );
            $this->redirect('SalesFunnel:Error');
        }

        $this->template->payment = $payment;
        $this->template->subscription = $payment->subscription;

        // removing session created in SalesFunnelFrontendPresenter
        $this->getSession('sales_funnel')->remove();
    }

    public function renderSelectCard(int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $user = $this->getUser();
        $this->checkPaymentBelongsToUser($user, $payment);

        $allUserCards = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($user->id)
            ->where(['payment_gateway.code = ?' => $payment->ref('payment_gateway')->code])
            ->where(['cid IS NOT NULL AND expires_at > ?' => new DateTime()])
            ->fetchAll();

        $cardsByExpiration = [];

        foreach ($allUserCards as $card) {
            $expiration = $card->expires_at->format(DateTime::RFC3339);
            if (!array_key_exists($expiration, $cardsByExpiration) || $cardsByExpiration[$expiration]->created_at < $card->created_at) {
                $cardsByExpiration[$expiration] = $card;
            }
        }

        $this->template->cards = array_values($cardsByExpiration);
        $this->template->payment = $payment;
    }

    public function getPayment()
    {
        // mega hack...
        if (isset($_POST['VS'])) {
            $this->VS = $_POST['VS'];
        }

        if (isset($this->VS)) {
            $payment = $this->paymentsRepository->findByVs($this->VS);
            return $payment;
        }
        $this->paymentLogsRepository->add(
            'ERROR',
            "Cannot load payment with VS '{$this->VS}'",
            $this->actualUrl()
        );
        $this->redirect('SalesFunnel:Error');
        return false;
    }

    private function actualUrl()
    {
        return $this->request->getUrl();
    }
}
