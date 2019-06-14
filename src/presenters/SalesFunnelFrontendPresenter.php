<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
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
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\ActualUserSubscription;
use Crm\UsersModule\Auth\Authorizator;
use Crm\UsersModule\Auth\InvalidEmailException;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Form\Renderer\BootstrapRenderer;
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
        RecurrentPaymentsRepository $recurrentPaymentsRepository
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
        $gateways['gopay'] = [];
        $gateways['gopay_recurrent'] = [];
//        dump($gateways);
//        die();
        $subscriptionTypes = $this->loadSubscriptionTypes($salesFunnel);
        if ($this->getUser()->id) {
            $subscriptionTypes = $this->filterSubscriptionTypes($subscriptionTypes, $this->getUser()->id);
        }
        if (count($subscriptionTypes) == 0) {
            $this->redirect('limitReached', $salesFunnel->id);
        }
        $addresses = [];
        $body = $salesFunnel->body;

        $body = file_get_contents(__DIR__ . '/../../../../app/modules/BurdaModule/seeders/sales_funnels/2019-05-apetit-month.html');

        $loader = new \Twig_Loader_Array([
            'funnel_template' => $body,
        ]);
        $twig = new \Twig_Environment($loader);

        $isLoggedIn = $this->getUser()->isLoggedIn();
        if ((isset($this->request->query['preview']) && $this->request->query['preview'] === 'no-user')
            && $this->getUser()->isAllowed('SalesFunnel:SalesFunnelsAdmin', 'preview')) {
            $isLoggedIn = false;
        }

        if ($isLoggedIn) {
            $addresses = $this->addressesRepository->addresses($this->usersRepository->find($this->getUser()->id), 'print');
        }

        $headEnd = $this->applicationConfig->get('header_block') . "\n\n" . $salesFunnel->head;

        $params = [
            'headEnd' => $headEnd,
            'funnel' => $salesFunnel,
            'isLogged' => $isLoggedIn,
            'gateways' => $gateways,
            'subscriptionTypes' => $subscriptionTypes,
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

        $this->emitter->emit(new SalesFunnelEvent($salesFunnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_SHOW));

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
            $subscriptionTypes[$subscriptionTypesRow->code] = $subscriptionTypesRow->toArray();
        }
        return $subscriptionTypes;
    }

    private function validateFunnel(ActiveRow $funnel = null)
    {
        if (isset($this->request->query['preview']) && $this->getUser()->isAllowed('SalesFunnel:SalesFunnelsAdmin', 'preview')) {
            return;
        }

        if (!$funnel) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS));
            $this->redirect('inactive');
        }

        if (!$funnel->is_active) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS));
            $this->redirect('inactive');
            return;
        }

        if ($funnel->start_at && $funnel->start_at > new DateTime()) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS));
            $this->redirect('inactive');
        }

        if ($funnel->end_at && $funnel->end_at < new DateTime()) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS));
            $this->redirect('inactive');
        }

        if ($funnel->only_logged && !$this->getUser()->isLoggedIn()) {
            $this->redirect('signIn', [
                'referer' => isset($_GET['referer']) ? $_GET['referer'] : '',
                'funnel' => isset($_GET['funnel']) ? $_GET['funnel'] : ''
            ]);
        }

        if ($funnel->only_not_logged && $this->getUser()->isLoggedIn()) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS));
            $this->redirect('noAccess', $funnel->id);
        }

        if ($funnel->segment_id) {
            $segmentRow = $funnel->segment;
            if ($segmentRow) {
                $segment = $this->segmentFactory->buildSegment($segmentRow->code);
                $inSegment = $segment->isIn('id', $this->getUser()->id);
                if (!$inSegment) {
                    $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS));
                    $this->redirect('noAccess', $funnel->id);
                }
            }
        }
    }

    private function validateSubscriptionType(ActiveRow $subscriptionType, ActiveRow $funnel)
    {
        if (!$subscriptionType || !$funnel->related('sales_funnels_subscription_types')->where(['subscription_type_id' => $subscriptionType->id])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('invalid');
        }

        if (!$subscriptionType->active) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('invalid');
        }

        $subscriptionTypes = $this->loadSubscriptionTypes($funnel);

        if (!isset($subscriptionTypes[$subscriptionType->code])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('invalid');
        }
    }

    private function validateGateway(ActiveRow $paymentGateway, ActiveRow $funnel)
    {
        if (!$paymentGateway || !$funnel->related('sales_funnels_payment_gateways')->where(['payment_gateway_id' => $paymentGateway->id])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('invalid');
        }

        if (!$paymentGateway->visible) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('invalid');
        }

        $gateways = $this->loadGateways($funnel);

        if (!isset($gateways[$paymentGateway->code])) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
            $this->redirect('invalid');
        }
    }

    private function user($email, $password, ActiveRow $funnel, $source, $referer, bool $needAuth = true)
    {
        if ($this->getUser() && $this->getUser()->isLoggedIn()) {
            return $this->userManager->loadUser($this->user);
        }

        $user = $this->userManager->loadUserByEmail($email);
        if ($user) {
            if ($needAuth) {
                $this->getUser()->getAuthenticator()->authenticate(['username' => $email, 'password' => $password]);
            }
        } else {
            $user = $this->userManager->addNewUser($email, true, $source, $referer);
            if (!$user) {
                $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR));
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

        if (!$funnel) {
            throw new BadRequestException('Funnel not found');
        }
        if ($funnel) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_FORM));
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

        if (!$this->validateSubscriptionTypeCounts($subscriptionType, $user)) {
            $this->redirect('limitReached', $funnel->id);
        }

        $addressId = filter_input(INPUT_POST, 'address_id');
        if ($addressId) {
            $address = $this->addressesRepository->find($addressId);
            if ($address->user_id != $user->id) {
                $address = null;
            }
        }

        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
        if ($additionalAmount) {
            $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
            if ($donationPaymentVat === null) {
                throw new \Exception("Config 'donation_vat_rate' is not set");
            }
            $paymentItemContainer->addItem(new DonationPaymentItem($this->translator->translate('payments.admin.donation'), $additionalAmount, $donationPaymentVat));
        }

        // let modules add own items to PaymentItemContainer before payment is created
        $this->emitter->emit(new PaymentItemContainerReadyEvent(
            $paymentItemContainer,
            $this->getHttpRequest()->getPost()
        ));

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
            $address
        );

        $customNote = false;
        if (isset($_POST['custom']) && is_array($_POST['custom']) && count($_POST['custom']) > 0) {
            foreach ($_POST['custom'] as $key => $value) {
                if ($value) {
                    $customNote .= "$key: $value\n";
                }
            }
        }
        if ($customNote) {
            $this->paymentsRepository->update($payment, ['note' => $customNote]);
        }

        $metaData = $this->getHttpRequest()->getPost('payment_metadata', []);

        $this->paymentsRepository->update($payment, ['sales_funnel_id' => $funnel->id]);
        $browserId = $_COOKIE['browser_id'] ?? null;
        $metaData = array_merge($metaData, $this->trackingParams());
        $metaData['newsletters_subscribe'] = (bool) filter_input(INPUT_POST, 'newsletters_subscribe');
        if ($browserId) {
            $metaData['browser_id'] = $browserId;
        }
        $this->paymentsRepository->addMeta($payment, $metaData);

        $this->getPaymentConfig($payment);

        $this->hermesEmitter->emit(new HermesMessage('sales-funnel', [
            'type' => 'payment',
            'user_id' => $user->id,
            'browser_id' => $browserId,
            'sales_funnel_id' => $funnel->id,
            'payment_id' => $payment->id,
        ]));

        $allowRedirect = true;
        if (isset($_POST['allow_redirect']) && ($_POST['allow_redirect'] == '0' || $_POST['allow_redirect'] == 'false')) {
            $allowRedirect = false;
        }

        if ($this->hasStoredCard($user, $payment->payment_gateway)) {
            $this->redirect('SalesFunnel:selectCard', $payment->id);
        }

        try {
            $result = $this->paymentProcessor->begin($payment, $allowRedirect);
            if ($result) {
                $this->sendJson(['status' => 'ok', 'url' => $result]);
            }
        } catch (CannotProcessPayment $err) {
            $this->redirect('error');
        }
    }

    private function hasStoredCard(ActiveRow $user, ActiveRow $paymentGateway)
    {
        // TODO remove admin check after test
        if ($user->role !== UsersRepository::ROLE_ADMIN) {
            return false;
        }

        $gateway = $this->gatewayFactory->getGateway($paymentGateway->code);

        // Only gateways supporting recurrent payments have support for stored cards
        if (!$gateway instanceof RecurrentPaymentInterface) {
            return false;
        }

        $usableRecurrentsCount = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($user->id)
            ->where(['payment_gateway.code = ?' => $paymentGateway->code])
            ->where(['cid IS NOT NULL AND expires_at > ?' => new DateTime()])
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
        $form = new Form();
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->addText('username', 'Email:')
            ->setType('email')
            ->setAttribute('autofocus')
            ->setRequired('Prosím zadajte Váš email')
            ->setAttribute('placeholder', 'Napríklad moj@email.sk');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Prosím zadajte Vaše heslo.')
            ->setAttribute('placeholder', 'Vaše heslo');

        $form->addHidden('referer');
        $form->addHidden('funnel');

        $form->addSubmit('send', 'Prihlásiť');

        $form->setDefaults([
            'remember' => true,
            'referer' => isset($_GET['referer']) ? $_GET['referer'] : '',
            'funnel' => isset($_GET['funnel']) ? $_GET['funnel'] : '',
        ]);

        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        return $form;
    }

    public function signInFormSucceeded($form, $values)
    {
        $this->getUser()->setExpiration('14 days', false);
        try {
            $this->getUser()->login(['username' => $values->username, 'password' => $values->password]);
            $this->getUser()->setAuthorizator($this->authorizator);
            $this->redirect('show', ['referer' => $values->referer, 'funnel' => $values->funnel]);
        } catch (AuthenticationException $e) {
            $form->addError($e->getMessage());
        }
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
}
