<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Request;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Repository\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsStatsRepository;
use Crm\SegmentModule\SegmentFactory;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\ActualUserSubscription;
use Crm\UsersModule\Auth\Authorizator;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Forms\SignInFormFactory;
use Crm\UsersModule\Repository\AddressesRepository;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\Emitter;

class SalesFunnelFrontendPresenter extends FrontendPresenter
{
    private $salesFunnelsRepository;

    private $subscriptionTypesRepository;

    private $salesFunnelsStatsRepository;

    private $salesFunnelsMetaRepository;

    private $paymentGatewaysRepository;

    private $paymentProcessor;

    private $paymentsRepository;

    private $segmentFactory;

    private $hermesEmitter;

    private $authorizator;

    private $actualUserSubscription;

    private $addressesRepository;

    private $userManager;

    private $gatewayFactory;

    private $recurrentPaymentsRepository;

    private $contentAccessRepository;

    private $signInFormFactory;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        SalesFunnelsStatsRepository $salesFunnelsStatsRepository,
        SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        PaymentProcessor $paymentProcessor,
        SegmentFactory $segmentFactory,
        ActualUserSubscription $actualUserSubscription,
        Emitter $hermesEmitter,
        Authorizator $authorizator,
        AddressesRepository $addressesRepository,
        UserManager $userManager,
        GatewayFactory $gatewayFactory,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ContentAccessRepository $contentAccessRepository,
        SignInFormFactory $signInFormFactory
    ) {
        parent::__construct();
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->salesFunnelsStatsRepository = $salesFunnelsStatsRepository;
        $this->salesFunnelsMetaRepository = $salesFunnelsMetaRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->paymentsRepository = $paymentsRepository;
        $this->segmentFactory = $segmentFactory;
        $this->actualUserSubscription = $actualUserSubscription;
        $this->hermesEmitter = $hermesEmitter;
        $this->authorizator = $authorizator;
        $this->addressesRepository = $addressesRepository;
        $this->userManager = $userManager;
        $this->gatewayFactory = $gatewayFactory;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->contentAccessRepository = $contentAccessRepository;
        $this->signInFormFactory = $signInFormFactory;
    }

    public function startup()
    {
        parent::startup();
        if ($this->action != 'default') {
            if ($this->layoutManager->exists($this->getLayoutName() . '_plain')) {
                $this->setLayout($this->getLayoutName() . '_plain');
            } else {
                $this->setLayout('sales_funnel_plain');
            }
        }
    }

    public function renderDefault($funnel)
    {
        $this->template->queryString = $_SERVER['QUERY_STRING'];
        $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($funnel);
        if (!$salesFunnel) {
            throw new BadRequestException('Funnel not found');
        }

        if ($salesFunnel->redirect_funnel) {
            $this->redirect('default', array_merge(['funnel' => $salesFunnel->redirect_funnel->url_key], $_GET));
        }

        $this->template->funnel = $salesFunnel;
        $this->template->referer = $this->getReferer();
    }

    public function renderShow($funnel, $referer = null, $values = null, $errors = null)
    {
        $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($funnel);
        if (!$salesFunnel) {
            throw new BadRequestException('Funnel not found');
        }
        $this->validateFunnel($salesFunnel);

        if (!$referer) {
            $referer = $this->getReferer();
        }

        $gateways = $this->loadGateways($salesFunnel);
        $subscriptionTypes = $this->loadSubscriptionTypes($salesFunnel);
        if ($this->getUser()->isLoggedIn()) {
            $subscriptionTypes = $this->filterSubscriptionTypes($subscriptionTypes, $this->getUser()->id);
        }
        if (count($subscriptionTypes) == 0) {
            $this->redirect('limitReached', $salesFunnel->id);
        }
        $addresses = [];
        $body = $salesFunnel->body;

        $loader = new \Twig\Loader\ArrayLoader([
            'funnel_template' => $body,
        ]);
        $twig = new \Twig\Environment($loader);

        $isLoggedIn = $this->getUser()->isLoggedIn();
        if ((isset($this->request->query['preview']) && $this->request->query['preview'] === 'no-user')
            && $this->getUser()->isAllowed('SalesFunnel:SalesFunnelsAdmin', 'preview')) {
            $isLoggedIn = false;
        }

        if ($isLoggedIn) {
            $addresses = $this->addressesRepository->addresses($this->usersRepository->find($this->getUser()->id), 'print');
        }

        $headEnd = $this->applicationConfig->get('header_block') . "\n\n" . $salesFunnel->head;

        $contentAccess = [];
        foreach ($subscriptionTypes as $index => $subscriptionType) {
            $contentAccess[$subscriptionType['code']] = $this->contentAccessRepository->allForSubscriptionType($subscriptionType)->fetchPairs('name', 'name');

            // casting to array for backwards compatibility and easier Twig access
            $subscriptionTypes[$index] = $subscriptionType->toArray();
        }

        $params = [
            'headEnd' => $headEnd,
            'funnel' => $salesFunnel,
            'isLogged' => $isLoggedIn,
            'gateways' => $gateways,
            'subscriptionTypes' => $subscriptionTypes,
            'contentAccess' => $contentAccess,
            'addresses' => $addresses,
            'meta' => $this->salesFunnelsMetaRepository->all($salesFunnel),
            'jsDomain' => $this->getJavascriptDomain(),
            'actualUserSubscription' => $this->actualUserSubscription,
            'referer' => urlencode($referer),
            'values' => $values ? Json::decode($values, Json::FORCE_ARRAY) : null,
            'errors' => $errors ? Json::decode($errors, Json::FORCE_ARRAY) : null,
            'backLink' => $this->storeRequest(),
        ];

        if ($isLoggedIn) {
            $params['email'] = $this->getUser()->getIdentity()->email;
            $params['user_id'] = $this->getUser()->getIdentity()->getId();
        }
        $template = $twig->render('funnel_template', $params);

        $ua = Request::getUserAgent();
        $this->emitter->emit(new SalesFunnelEvent($salesFunnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_SHOW, $ua));

        $userId = null;
        if ($this->getUser()->isLoggedIn()) {
            $userId = $this->getUser()->getIdentity()->id;
        }
        $browserId = (isset($_COOKIE['browser_id']) ? $_COOKIE['browser_id'] : null);

        $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
            'type' => 'checkout',
            'user_id' => $userId,
            'browser_id' => $browserId,
            'sales_funnel_id' => $salesFunnel->id,
            'source' => $this->trackingParams(),
        ]));

        $this->sendResponse(new TextResponse($template));
    }

    private function loadGateways(ActiveRow $salesFunnel)
    {
        $gateways = [];
        $gatewayRows = $this->salesFunnelsRepository->getSalesFunnelGateways($salesFunnel);
        /** @var ActiveRow $gatewayRow */
        foreach ($gatewayRows as $gatewayRow) {
            $gateways[$gatewayRow->code] = $gatewayRow->toArray();
        }
        return $gateways;
    }

    private function loadSubscriptionTypes(ActiveRow $salesFunnel)
    {
        $subscriptionTypes = [];
        $subscriptionTypesRows = $this->salesFunnelsRepository->getSalesFunnelSubscriptionTypes($salesFunnel);
        /** @var ActiveRow $subscriptionTypesRow */
        foreach ($subscriptionTypesRows as $subscriptionTypesRow) {
            $subscriptionTypes[$subscriptionTypesRow->code] = $subscriptionTypesRow;
        }
        return $subscriptionTypes;
    }

    private function validateFunnel(ActiveRow $funnel = null)
    {
        if (isset($this->request->query['preview']) && $this->getUser()->isAllowed('SalesFunnel:SalesFunnelsAdmin', 'preview')) {
            return;
        }

        $ua = Request::getUserAgent();

        if (!$funnel) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('inactive');
        }

        if (!$funnel->is_active) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('inactive');
            return;
        }

        if ($funnel->start_at && $funnel->start_at > new DateTime()) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('inactive');
        }

        if ($funnel->end_at && $funnel->end_at < new DateTime()) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('inactive');
        }

        if ($funnel->only_logged && !$this->getUser()->isLoggedIn()) {
            $this->redirectOrSendJson('signIn', [
                'referer' => $this->getParameter('referer'),
                'funnel' => $this->getParameter('funnel'),
            ]);
        }

        if ($funnel->only_not_logged && $this->getUser()->isLoggedIn()) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('noAccess', $funnel->id);
        }

        if ($this->getUser()->isLoggedIn() && $this->validateFunnelSegment($funnel, $this->getUser()->getId()) === false) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('noAccess', $funnel->id);
        }
    }

    private function validateFunnelSegment(ActiveRow $funnel, int $userId): bool
    {
        if ($funnel->segment_id) {
            $segmentRow = $funnel->segment;
            if ($segmentRow) {
                $segment = $this->segmentFactory->buildSegment($segmentRow->code);

                return $segment->isIn('id', $userId);
            }
        }

        return true;
    }

    private function validateSubscriptionType(ActiveRow $subscriptionType, ActiveRow $funnel)
    {
        $ua = Request::getUserAgent();

        if (!$subscriptionType || !$funnel->related('sales_funnels_subscription_types')->where(['subscription_type_id' => $subscriptionType->id])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }

        if (!$subscriptionType->active) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }

        $subscriptionTypes = $this->loadSubscriptionTypes($funnel);

        if (!isset($subscriptionTypes[$subscriptionType->code])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }
    }

    private function validateGateway(ActiveRow $paymentGateway, ActiveRow $funnel)
    {
        $ua = Request::getUserAgent();

        if (!$paymentGateway || !$funnel->related('sales_funnels_payment_gateways')->where(['payment_gateway_id' => $paymentGateway->id])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }

        if (!$paymentGateway->visible) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }

        $gateways = $this->loadGateways($funnel);

        if (!isset($gateways[$paymentGateway->code])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }
    }

    private function user($email, $password, ActiveRow $funnel, $source, $referer, bool $needAuth = true)
    {
        $ua = Request::getUserAgent();

        if ($this->getUser() && $this->getUser()->isLoggedIn()) {
            return $this->userManager->loadUser($this->user);
        }

        $user = $this->userManager->loadUserByEmail($email);
        if ($user) {
            if ($needAuth) {
                $this->getUser()->getAuthenticator()->authenticate(['username' => $email, 'password' => $password]);
            }
        } else {
            $user = $this->userManager->addNewUser($email, true, $source, $referer, true, null, false);
            if (!$user) {
                $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
                $this->redirect('error');
            }
            $this->usersRepository->update($user, [
                'sales_funnel_id' => $funnel->id,
            ]);
        }

        return $user;
    }

    public function renderSubmit()
    {
        $funnel = $this->salesFunnelsRepository->findByUrlKey(filter_input(INPUT_POST, 'funnel_url_key'));

        $ua = Request::getUserAgent();

        if (!$funnel) {
            throw new BadRequestException('Funnel not found');
        }
        if ($funnel) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_FORM, $ua));
        }

        $this->validateFunnel($funnel);

        $referer = $this->getReferer();

        $address = null;
        $email = filter_input(INPUT_POST, 'email');
        $password = filter_input(INPUT_POST, 'password');
        $needAuth = true;
        if (isset($_POST['auth']) && ($_POST['auth'] == '0' || $_POST['auth'] == 'false')) {
            $needAuth = false;
        }

        $subscriptionTypeCode = filter_input(INPUT_POST, 'subscription_type');
        $subscriptionType = $this->subscriptionTypesRepository->findBy('code', $subscriptionTypeCode);
        $this->validateSubscriptionType($subscriptionType, $funnel);

        $paymentGateway = $this->paymentGatewaysRepository->findByCode(filter_input(INPUT_POST, 'payment_gateway'));
        $this->validateGateway($paymentGateway, $funnel);

        $additionalAmount = 0;
        $additionalType = null;
        if (isset($_POST['additional_amount']) && floatval($_POST['additional_amount']) > 0) {
            $additionalAmount = floatval($_POST['additional_amount']);
            $additionalType = 'single';
            if (isset($_POST['additional_type']) && $_POST['additional_type'] == 'recurrent') {
                $additionalType = 'recurrent';
            }
        }

        $source = $this->getHttpRequest()->getPost('registration_source', 'funnel');

        $user = null;
        try {
            $userError = null;
            $user = $this->user($email, $password, $funnel, $source, $referer, $needAuth);
        } catch (AuthenticationException $e) {
            $userError = Json::encode(['password' => $this->translator->translate("sales_funnel.frontend.invalid_credentials.title")]);
        } catch (InvalidEmailException $e) {
            $userError = Json::encode(['email' => $this->translator->translate("sales_funnel.frontend.invalid_email.title")]);
        }

        if ($userError) {
            $this->redirect(
                'show',
                $funnel->url_key,
                $referer,
                Json::encode([
                    'email' => $email,
                    'payment_gateway' => $paymentGateway->code,
                    'additional_amount' => $additionalAmount,
                    'additional_type' => $additionalType,
                    'subscription_type' => filter_input(INPUT_POST, 'subscription_type'),
                ]),
                $userError
            );
        }

        if ($this->validateFunnelSegment($funnel, $user->id) === false) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('noAccess', $funnel->id);
        }

        if (!$this->validateSubscriptionTypeCounts($subscriptionType, $user)) {
            $this->redirectOrSendJson('limitReached', $funnel->id);
        }

        $addressId = filter_input(INPUT_POST, 'address_id');
        if ($addressId) {
            $address = $this->addressesRepository->find($addressId);
            if ($address->user_id != $user->id) {
                $address = null;
            }
        }

        // container items
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
        if ($additionalAmount) {
            $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
            if ($donationPaymentVat === null) {
                throw new \Exception("Config 'donation_vat_rate' is not set");
            }
            $paymentItemContainer->addItem(new DonationPaymentItem($this->translator->translate('payments.admin.donation'), $additionalAmount, (int)$donationPaymentVat));
        }

        // let modules add own items to PaymentItemContainer before payment is created
        $this->emitter->emit(new PaymentItemContainerReadyEvent(
            $paymentItemContainer,
            $this->getHttpRequest()->getPost()
        ));

        // prepare payment meta
        $metaData = [];
        $metaData = array_merge($metaData, $this->trackingParams());
        $metaData['newsletters_subscribe'] = (bool) filter_input(INPUT_POST, 'newsletters_subscribe');

        foreach ($this->getHttpRequest()->getPost('payment_metadata', []) as $key => $submittedMeta) {
            if ($submittedMeta !== "") {
                $metaData[$key] = $submittedMeta;
            }
        }

        $browserId = $_COOKIE['browser_id'] ?? null;
        if ($browserId) {
            $metaData['browser_id'] = $browserId;
        }

        $payment = $this->paymentsRepository->add(
            $subscriptionType,
            $paymentGateway,
            $user,
            $paymentItemContainer,
            $referer,
            null,
            null,
            null,
            null,
            $additionalAmount,
            $additionalType,
            null,
            $address,
            false,
            $metaData
        );

        $this->paymentsRepository->update($payment, ['sales_funnel_id' => $funnel->id]);
        $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
            'type' => 'payment',
            'user_id' => $user->id,
            'browser_id' => $browserId,
            'sales_funnel_id' => $funnel->id,
            'payment_id' => $payment->id,
        ]));

        if ($this->hasStoredCard($user, $payment->payment_gateway)) {
            $this->redirectOrSendJson(':Payments:Recurrent:selectCard', $payment->id);
        }

        try {
            $result = $this->paymentProcessor->begin($payment, $this->isAllowedRedirect());
            if ($result) {
                $this->sendJson(['status' => 'ok', 'url' => $result]);
            }
        } catch (CannotProcessPayment $err) {
            $this->redirectOrSendJson('error');
        }
    }

    private function hasStoredCard(ActiveRow $user, ActiveRow $paymentGateway)
    {
        $gateway = $this->gatewayFactory->getGateway($paymentGateway->code);

        // Only gateways supporting recurrent payments have support for stored cards
        if (!$gateway instanceof RecurrentPaymentInterface) {
            return false;
        }

        $usableRecurrentsCount = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($user->id)
            ->where(['payment_gateway.code = ?' => $paymentGateway->code])
            ->where(['cid IS NOT NULL AND expires_at > ?' => new DateTime()])
            ->order('id DESC, charge_at DESC')
            ->count();

        return $usableRecurrentsCount > 0;
    }

    public function renderNoAccess($id = null)
    {
        if ($id) {
            $funnel = $this->salesFunnelsRepository->find($id);
            if ($funnel && $funnel->no_access_html) {
                $this->template->noAccessHtml = $funnel->no_access_html;
            }
        }
    }

    public function renderLimitReached($id = null)
    {
        if ($id) {
            $funnel = $this->salesFunnelsRepository->find($id);
            if ($funnel && $funnel->no_access_html) {
                $this->template->noAccessHtml = $funnel->no_access_html;
            }
        }
    }

    public function renderError($id = null)
    {
        if ($id) {
            $funnel = $this->salesFunnelsRepository->find($id);
            if ($funnel && $funnel->error_html) {
                $this->template->errorHtml = $funnel->error_html;
            }
        }
    }

    protected function createComponentSignInForm()
    {
        $form = $this->signInFormFactory->create();

        $form->addHidden('referer');
        $form->addHidden('funnel');
        $form->setDefaults([
            'referer' => $this->getParameter('referer'),
            'funnel' => $this->getParameter('funnel'),
        ]);

        $this->signInFormFactory->onAuthenticated = function ($form, $values, $user) {
            $this->redirect('show', ['referer' => $values->referer, 'funnel' => $values->funnel]);
        };
        return $form;
    }

    private function filterSubscriptionTypes(array $subscriptionTypes, int $userId)
    {
        $userSubscriptionsTypesCount = $this->subscriptionsRepository->userSubscriptionTypesCounts($userId, array_column($subscriptionTypes, 'id'));
        foreach ($subscriptionTypes as $code => $subscriptionType) {
            if (!isset($userSubscriptionsTypesCount[$subscriptionType['id']])) {
                continue;
            }

            if ($subscriptionType['limit_per_user'] !== null
                && $subscriptionType['limit_per_user'] <= $userSubscriptionsTypesCount[$subscriptionType['id']]
            ) {
                unset($subscriptionTypes[$code]);
            }
        }
        return $subscriptionTypes;
    }

    private function validateSubscriptionTypeCounts(ActiveRow $subscriptionType, ActiveRow $user)
    {
        if (!$subscriptionType->limit_per_user) {
            return true;
        }

        $userSubscriptionsTypesCount = $this->subscriptionsRepository->userSubscriptionTypesCounts($user->id, [$subscriptionType->id]);
        if (!isset($userSubscriptionsTypesCount[$subscriptionType->id])) {
            return true;
        }
        if ($subscriptionType->limit_per_user <= $userSubscriptionsTypesCount[$subscriptionType->id]) {
            return false;
        }
        return true;
    }

    private function isAllowedRedirect(): bool
    {
        if (isset($_POST['allow_redirect']) && ($_POST['allow_redirect'] == '0' || $_POST['allow_redirect'] == 'false')) {
            return false;
        }

        return true;
    }

    private function redirectOrSendJson($destination = null, $args = []): void
    {
        if ($this->isAllowedRedirect() === true) {
            $this->redirect($destination, $args);
        }

        $this->sendJson([
            'status' => 'error',
            'url' => $this->link($destination, $args)
        ]);
    }
}
