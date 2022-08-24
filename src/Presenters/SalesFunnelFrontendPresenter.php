<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Events\AuthenticatedAccessRequiredEvent;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\ApplicationModule\Request;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\ProcessResponse;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SalesFunnelModule\DataProvider\SalesFunnelPaymentFormDataProviderInterface;
use Crm\SalesFunnelModule\DataProvider\SalesFunnelVariablesDataProviderInterface;
use Crm\SalesFunnelModule\DataProvider\TrackerDataProviderInterface;
use Crm\SalesFunnelModule\DataProvider\ValidateUserFunnelAccessDataProviderInterface;
use Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Repository\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsStatsRepository;
use Crm\SegmentModule\SegmentFactory;
use Crm\SegmentModule\SegmentFactoryInterface;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\ActualUserSubscription;
use Crm\SubscriptionsModule\Subscription\SubscriptionTypeHelper;
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
    public const DEFAULT_ACTION_LAYOUT_NAME = 'default_action_layout_name';

    private SalesFunnelsRepository $salesFunnelsRepository;
    private SubscriptionTypesRepository $subscriptionTypesRepository;
    private SalesFunnelsMetaRepository $salesFunnelsMetaRepository;
    private PaymentGatewaysRepository $paymentGatewaysRepository;
    private PaymentProcessor $paymentProcessor;
    private PaymentsRepository $paymentsRepository;
    private SegmentFactory $segmentFactory;
    private Emitter $hermesEmitter;
    private ActualUserSubscription $actualUserSubscription;
    private AddressesRepository $addressesRepository;
    private UserManager $userManager;
    private GatewayFactory $gatewayFactory;
    private RecurrentPaymentsRepository $recurrentPaymentsRepository;
    private ContentAccessRepository $contentAccessRepository;
    private SignInFormFactory $signInFormFactory;
    private DataProviderManager $dataProviderManager;
    private SubscriptionTypeHelper $subscriptionTypeHelper;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        PaymentProcessor $paymentProcessor,
        SegmentFactoryInterface $segmentFactory,
        ActualUserSubscription $actualUserSubscription,
        Emitter $hermesEmitter,
        AddressesRepository $addressesRepository,
        UserManager $userManager,
        GatewayFactory $gatewayFactory,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ContentAccessRepository $contentAccessRepository,
        SignInFormFactory $signInFormFactory,
        DataProviderManager $dataProviderManager,
        SubscriptionTypeHelper $subscriptionTypeHelper
    ) {
        parent::__construct();
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->salesFunnelsMetaRepository = $salesFunnelsMetaRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentProcessor = $paymentProcessor;
        $this->paymentsRepository = $paymentsRepository;
        $this->segmentFactory = $segmentFactory;
        $this->actualUserSubscription = $actualUserSubscription;
        $this->hermesEmitter = $hermesEmitter;
        $this->addressesRepository = $addressesRepository;
        $this->userManager = $userManager;
        $this->gatewayFactory = $gatewayFactory;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->contentAccessRepository = $contentAccessRepository;
        $this->signInFormFactory = $signInFormFactory;
        $this->dataProviderManager = $dataProviderManager;
        $this->subscriptionTypeHelper = $subscriptionTypeHelper;
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
        $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($funnel);
        if (!$salesFunnel) {
            throw new BadRequestException('Funnel not found');
        }

        if ($salesFunnel->redirect_funnel) {
            $this->redirect('default', array_merge(['funnel' => $salesFunnel->redirect_funnel->url_key], $_GET));
        }

        $layoutName = $this->salesFunnelsMetaRepository->get($salesFunnel, self::DEFAULT_ACTION_LAYOUT_NAME);
        if ($layoutName && $this->layoutManager->exists($layoutName)) {
            $this->setLayout($layoutName);
        }

        $this->template->funnel = $salesFunnel;
        $this->template->referer = $this->getReferer();
        $this->template->host = $this->getHttpRequest()->getUrl()->getHostUrl();

        $this->template->queryParams = $this->request->getQuery();
        unset($this->template->queryParams['referer']); // already passed separately

        /** @var SalesFunnelVariablesDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'sales_funnel.dataprovider.template_variables',
            SalesFunnelVariablesDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            foreach ($provider->provide([
                SalesFunnelVariablesDataProviderInterface::PARAM_SALES_FUNNEL => $salesFunnel
            ]) as $name => $value) {
                $this->template->$name = $value;
            }
        }
    }

    public function renderShow($funnel, $referer = null, $values = null, $errors = null)
    {
        $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($funnel);
        if (!$salesFunnel) {
            throw new BadRequestException('Funnel not found');
        }
        $this->validateFunnel($salesFunnel);

        $isLoggedIn = $this->getUser()->isLoggedIn();

        if (!$referer) {
            $referer = $this->getReferer();
        }

        $gateways = $this->loadGateways($salesFunnel);
        $subscriptionTypes = $this->getValidSubscriptionTypes($salesFunnel);

        $addresses = [];
        $body = $salesFunnel->body;

        $loader = new \Twig\Loader\ArrayLoader([
            'funnel_template' => $body,
        ]);
        $twig = new \Twig\Environment($loader);

        if (($this->request->query['preview'] ?? null) === 'no-user' && $this->isValidPreview()) {
            $isLoggedIn = false;
        }

        if ($isLoggedIn) {
            $addresses = $this->addressesRepository->addresses($this->usersRepository->find($this->getUser()->id), 'print');
        }

        $headEnd = $this->applicationConfig->get('header_block') . "\n\n" . $this->applicationConfig->get('sales_funnel_header_block') . "\n\n" . $salesFunnel->head_script;

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
            'locale' => $this->translator->getLocale(),
        ];

        /** @var SalesFunnelVariablesDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'sales_funnel.dataprovider.twig_variables',
            SalesFunnelVariablesDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            foreach ($provider->provide([
                SalesFunnelVariablesDataProviderInterface::PARAM_SALES_FUNNEL => $salesFunnel
            ]) as $name => $value) {
                $params[$name] = $value;
            }
        }

        if ($isLoggedIn) {
            $params['email'] = $this->getUser()->getIdentity()->email;
            $params['user_id'] = $this->getUser()->getIdentity()->getId();
        }
        $template = $twig->render('funnel_template', $params);

        $ua = Request::getUserAgent();
        $this->emitter->emit(new SalesFunnelEvent($salesFunnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_SHOW, $ua));

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

    private function getValidSubscriptionTypes(ActiveRow $salesFunnel)
    {
        $subscriptionTypes = $this->loadSubscriptionTypes($salesFunnel);

        $isLoggedIn = $this->getUser()->isLoggedIn();
        if ($isLoggedIn && !$this->isValidPreview()) {
            $subscriptionTypes = $this->filterSubscriptionTypes($subscriptionTypes, $this->getUser()->id);
            if (count($subscriptionTypes) === 0) {
                $this->redirect('limitReached', $salesFunnel->id);
            }
        }
        if (count($subscriptionTypes) === 0) {
            if ($this->isValidPreview()) {
                $this->redirect('noSubscriptionTypes', $salesFunnel->id);
            } else {
                $this->redirect('limitReached', $salesFunnel->id);
            }
        }

        return $subscriptionTypes;
    }

    private function validateFunnel(ActiveRow $funnel = null)
    {
        if ($this->isValidPreview()) {
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

        $this->validateFunnelPurchaseLimit($funnel);

        if ($this->getUser()->isLoggedIn() && $this->validateFunnelSegment($funnel, $this->getUser()->getId()) === false) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('noAccess', $funnel->id);
        }

        if ($this->getUser()->isLoggedIn()) {
            $this->validateFunnelLimitPerUserCount($funnel, $this->getUser()->id);
        }

        /** @var ValidateUserFunnelAccessDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('salesfunnel.dataprovider.validate_funnel', ValidateUserFunnelAccessDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $canAccessFunnel = $provider->provide([
                'user' => $this->getUser()->isLoggedIn() ? $this->getUser()->getIdentity() : null,
                'sales_funnel' => $funnel,
            ]);
            if (!$canAccessFunnel) {
                $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
                $this->redirectOrSendJson('noAccess', $funnel->id);
            }
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

    private function user($email, $password, ActiveRow $funnel, $source, $referer, bool $needAuth = true): ActiveRow
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
        $locale = filter_input(INPUT_POST, 'locale');
        $needAuth = true;
        if (isset($_POST['auth']) && ($_POST['auth'] == '0' || $_POST['auth'] == 'false')) {
            $needAuth = false;
        }

        if ($locale && !in_array($locale, $this->translator->getAvailableLocales(), true)) {
            $locale = null; // accept only valid locales
        }

        $subscriptionTypeCode = filter_input(INPUT_POST, 'subscription_type');
        $subscriptionType = $this->subscriptionTypesRepository->findBy('code', $subscriptionTypeCode);
        $this->validateSubscriptionType($subscriptionType, $funnel);

        $paymentGateway = $this->paymentGatewaysRepository->findByCode(filter_input(INPUT_POST, 'payment_gateway'));
        $this->validateGateway($paymentGateway, $funnel);

        $additionalAmount = 0;
        $additionalType = null;
        if (isset($_POST['additional_amount']) && (float) $_POST['additional_amount'] > 0) {
            $additionalAmount = (float) $_POST['additional_amount'];
            $additionalType = 'single';
            if (isset($_POST['additional_type']) && $_POST['additional_type'] == 'recurrent') {
                $additionalType = 'recurrent';
            }
        }

        $source = $this->getHttpRequest()->getPost('registration_source') ?? 'funnel';

        $user = null;
        try {
            $userError = null;
            $user = $this->user($email, $password, $funnel, $source, $referer, $needAuth);

            if ($locale && $user->locale !== $locale) {
                $this->usersRepository->update($user, ['locale' => $locale]);
                $user = $this->usersRepository->find($user->id);
            }
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

        $this->validateFunnelLimitPerUserCount($funnel, $user->id);

        $this->validateFunnelPurchaseLimit($funnel);

        if ($this->validateFunnelSegment($funnel, $user->id) === false) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $user, SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
            $this->redirectOrSendJson('noAccess', $funnel->id);
        }

        /** @var ValidateUserFunnelAccessDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('salesfunnel.dataprovider.validate_funnel', ValidateUserFunnelAccessDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $canAccessFunnel = $provider->provide([
                'user' => $user,
                'sales_funnel' => $funnel,
            ]);
            if (!$canAccessFunnel) {
                $this->emitter->emit(new SalesFunnelEvent($funnel, $user, SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $ua));
                $this->redirectOrSendJson('noAccess', $funnel->id);
            }
        }

        if (!$this->subscriptionTypeHelper->validateSubscriptionTypeCounts($subscriptionType, $user)) {
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
        $metaData['newsletters_subscribe'] = (bool)filter_input(INPUT_POST, 'newsletters_subscribe');

        foreach ($this->getHttpRequest()->getPost('payment_metadata') ?? [] as $key => $submittedMeta) {
            if ($submittedMeta !== "") {
                $metaData[$key] = $submittedMeta;
            }
        }

        $trackerParams = [];
        /** @var TrackerDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'sales_funnel.dataprovider.tracker',
            TrackerDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $trackerParams[] = $provider->provide();
        }
        $trackerParams = array_merge([], ...$trackerParams);

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
            array_merge($metaData, $trackerParams)
        );

        $this->paymentsRepository->update($payment, ['sales_funnel_id' => $funnel->id]);

        $providers = $this->dataProviderManager->getProviders('salesfunnel.dataprovider.payment_form_data', SalesFunnelPaymentFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $provider->provide(['payment' => $payment, 'post_data' => $this->request->post]);
        }

        $eventParams = [
            'type' => 'payment',
            'user_id' => $user->id,
            'sales_funnel_id' => $funnel->id,
            'payment_id' => $payment->id,
        ];
        $this->hermesEmitter->emit(
            new HermesMessage(
                'sales-funnel',
                array_merge($eventParams, $trackerParams)
            )
        );

        if ($this->recurrentPaymentsRepository->hasStoredCard($user, $payment->payment_gateway)) {
            $this->redirectOrSendJson(':Payments:Recurrent:selectCard', $payment->id);
        }

        try {
            $result = $this->paymentProcessor->begin($payment, $this->isAllowedRedirect());
            if ($result) {
                if (is_string($result)) { // backward compatibility
                    $result = new ProcessResponse('url', $result);
                }
                $this->sendJson([
                    'status' => 'ok',
                    'type' => $result->getType(),
                    $result->getType() => $result->getData(),
                ]);
            }
        } catch (CannotProcessPayment $err) {
            $this->redirectOrSendJson('error');
        }
    }

    public function renderSignIn($referer, $funnel)
    {
        $this->emitter->emit(new AuthenticatedAccessRequiredEvent());

        // user might have been logged in one of the event handlers
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('show', [
                'referer' => $referer,
                'funnel' => $funnel,
            ]);
        }
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

    public function renderNoSubscriptionTypes($id = null)
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

    private function validateFunnelLimitPerUserCount(ActiveRow $funnel, $userId)
    {
        if ($funnel->limit_per_user && $userId) {
            $salesFunnelUserCount = $this->salesFunnelsRepository->getAllUserSalesFunnelPurchases(
                $userId,
                $funnel->id
            )->count(':payments.id');
            if ($salesFunnelUserCount >= $funnel->limit_per_user) {
                $this->redirectOrSendJson('limitReached', $funnel->id);
            }
        }
        return true;
    }

    private function validateFunnelPurchaseLimit(ActiveRow $funnel)
    {
        $purchaseLimit = $this->salesFunnelsMetaRepository->get($funnel, 'funnel_purchase_limit');
        if ($purchaseLimit) {
            $purchases = $this->salesFunnelsRepository->getAllSalesFunnelPurchases($funnel->id)
                                                      ->count(':payments.id');

            if ($purchases >= (int) $purchaseLimit) {
                $this->redirectOrSendJson('limitReached', $funnel->id);
            }
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

    private function isValidPreview(): bool
    {
        return isset($this->request->query['preview']) &&
            $this->getUser()->isAllowed('SalesFunnel:SalesFunnelsAdmin', 'preview');
    }
}
