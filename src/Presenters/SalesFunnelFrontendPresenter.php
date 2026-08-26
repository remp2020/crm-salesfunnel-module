<?php

namespace Crm\SalesFunnelModule\Presenters;

use Crm\ApplicationModule\Events\AuthenticatedAccessRequiredEvent;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Authenticator\LoginCredentials;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Request;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Models\CannotProcessPayment;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\ProcessResponse;
use Crm\PaymentsModule\Models\Gateways\RecurrentAuthorizationInterface;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelPaymentFormDataProviderInterface;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelVariablesDataProviderInterface;
use Crm\SalesFunnelModule\DataProviders\TrackerDataProviderInterface;
use Crm\SalesFunnelModule\DataProviders\UserStoredCardDataProviderInterface;
use Crm\SalesFunnelModule\DataProviders\ValidateUserFunnelAccessDataProviderInterface;
use Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent;
use Crm\SalesFunnelModule\Events\SalesFunnelEvent;
use Crm\SalesFunnelModule\Models\SalesFunnelRenderer;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsStatsRepository;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionTypeHelper;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Forms\SignInFormFactory;
use Crm\UsersModule\Models\Auth\InvalidEmailException;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UserActionsLogRepository;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\Emitter;
use Tracy\Debugger;

class SalesFunnelFrontendPresenter extends FrontendPresenter
{
    public const string DEFAULT_ACTION_LAYOUT_NAME = 'default_action_layout_name';

    public function __construct(
        private SalesFunnelsRepository $salesFunnelsRepository,
        private SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private PaymentGatewaysRepository $paymentGatewaysRepository,
        private PaymentsRepository $paymentsRepository,
        private PaymentProcessor $paymentProcessor,
        private SegmentFactoryInterface $segmentFactory,
        private Emitter $hermesEmitter,
        private AddressesRepository $addressesRepository,
        private UserManager $userManager,
        private SignInFormFactory $signInFormFactory,
        private DataProviderManager $dataProviderManager,
        private SubscriptionTypeHelper $subscriptionTypeHelper,
        private UnclaimedUser $unclaimedUser,
        private SalesFunnelRenderer $salesFunnelRenderer,
        private OneStopShop $oneStopShop,
        private UserActionsLogRepository $userActionsLogRepository,
        private GatewayFactory $gatewayFactory,
    ) {
        parent::__construct();
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

    /**
     * @deprecated 4.0 No longer supported way of rendering sales funnels
     * @see crm-salesfunnel-module/README.md#iframe-deprecation-in-sales-funnels
     */
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
            SalesFunnelVariablesDataProviderInterface::class,
        );
        foreach ($providers as $provider) {
            foreach ($provider->provide([
                SalesFunnelVariablesDataProviderInterface::PARAM_SALES_FUNNEL => $salesFunnel,
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

        $previewMode = $this->getPreviewMode();
        $isPreview = $previewMode !== null;

        if (!$isPreview && $salesFunnel->redirect_funnel) {
            $this->redirect('show', array_merge(['funnel' => $salesFunnel->redirect_funnel->url_key], $_GET));
        }

        $this->validateFunnel($salesFunnel);

        if (!$referer) {
            $referer = $this->getReferer();
        }

        $user = null;
        if ($this->getUser()->isLoggedIn() && $previewMode !== 'no-user') {
            $user = $this->userManager->loadUser($this->getUser());
        }

        $subscriptionTypes = $this->salesFunnelRenderer->getPurchasableSubscriptionTypes($salesFunnel, $isPreview ? null : $user);
        if ($subscriptionTypes === []) {
            $this->redirect($isPreview ? 'noSubscriptionTypes' : 'limitReached', $salesFunnel->id);
        }

        $template = $this->salesFunnelRenderer->render(
            salesFunnel: $salesFunnel,
            subscriptionTypes: $subscriptionTypes,
            user: $user,
            referer: $referer,
            values: $values ? Json::decode($values, Json::FORCE_ARRAY) : null,
            errors: $errors ? Json::decode($errors, Json::FORCE_ARRAY) : null,
        );

        // Admin previews would increase show stats and fire `sales_funnel` scenario triggers.
        if (!$isPreview) {
            $this->emitter->emit(new SalesFunnelEvent(
                $salesFunnel,
                $this->getUser(),
                SalesFunnelsStatsRepository::TYPE_SHOW,
                Request::getUserAgent(),
            ));
        }

        $this->sendResponse(new TextResponse($template));
    }

    private function validateFunnel(?ActiveRow $funnel = null)
    {
        if ($this->getPreviewMode() !== null) {
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
            $this->onlyLoggedIn();
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
                $this->displayCantAccessFunnel($funnel, $ua);
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

    private function validateSubscriptionType(?ActiveRow $subscriptionType, ActiveRow $funnel)
    {
        $ua = Request::getUserAgent();

        if (!$subscriptionType || !$subscriptionType->active) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }

        $salesFunnelHasSubscriptionType = $funnel->related('sales_funnels_subscription_types')
                ->where(['subscription_type_id' => $subscriptionType->id])
                ->count('*') > 0;
        if (!$salesFunnelHasSubscriptionType) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }
    }

    private function validateGateway(?ActiveRow $paymentGateway, ActiveRow $funnel)
    {
        $ua = Request::getUserAgent();

        if (!$paymentGateway || !$paymentGateway->visible) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }

        $salesFunnelHasPaymentGateway = $funnel->related('sales_funnels_payment_gateways')
            ->where(['payment_gateway_id' => $paymentGateway->id])
            ->count('*');
        if (!$salesFunnelHasPaymentGateway) {
            $this->emitter->emit(new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_ERROR, $ua));
            $this->redirectOrSendJson('invalid');
        }
    }

    private function user($email, $password, ActiveRow $funnel, $source, $referer): ActiveRow
    {
        $ua = Request::getUserAgent();

        if ($this->getUser()->isLoggedIn()) {
            return $this->userManager->loadUser($this->user);
        }

        $user = $this->userManager->loadUserByEmail($email);
        if ($user) {
            if ($this->unclaimedUser->isUnclaimedUser($user)) {
                $user = $this->unclaimedUser->makeUnclaimedUserRegistered(
                    user: $user,
                    source: $source,
                    referer: $referer,
                );
                $this->usersRepository->update($user, [
                    'sales_funnel_id' => $funnel->id,
                ]);
            } else {
                $this->getUser()->setExpiration('14 days', false);
                $this->getUser()->login(new LoginCredentials(['username' => $email, 'password' => $password]));
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

            $this->getUser()->login(new LoginCredentials(['user' => $user, 'autoLogin' => true]));
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
            $user = $this->user($email, $password, $funnel, $source, $referer);

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
                $userError,
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
                $this->displayCantAccessFunnel($funnel, $ua);
            }
        }

        if (!$this->subscriptionTypeHelper->validateSubscriptionTypeCounts($subscriptionType, $user)) {
            $this->redirectOrSendJson('limitReached', $funnel->id);
        }

        $addressId = (int) filter_input(INPUT_POST, 'address_id');
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
            $paymentItemContainer->addItem(new DonationPaymentItem($this->translator->translate('payments.admin.donation'), (float) $additionalAmount, (int) $donationPaymentVat));
        }

        // let modules add own items to PaymentItemContainer before payment is created
        $this->emitter->emit(new PaymentItemContainerReadyEvent(
            $paymentItemContainer,
            $user,
            $this->getHttpRequest()->getPost(),
        ));
        $gateway = $this->gatewayFactory->getGateway($paymentGateway->code);
        if ($gateway instanceof RecurrentAuthorizationInterface) {
            $paymentItemContainer->setZeroPriceAllowed();
        }

        $resolvedCountry = null;
        try {
            $resolvedCountry = $this->oneStopShop->resolveCountry(
                user: $user,
                selectedCountryCode: $this->request->getPost('payment_country'),
                paymentAddress: $address,
                paymentItemContainer: $paymentItemContainer,
                formParams: $this->request->post,
            );
        } catch (OneStopShopCountryConflictException $e) {
            Debugger::log('Sales funnel - OSS conflict: ' . $e->getMessage() . '. User will be redirected to resolution screen.', Debugger::WARNING);
            $this->userActionsLogRepository->add($user->id, 'funnel.one_stop_shop.conflict', ['exception' => $e->getMessage()]);
            $this->redirectOrSendJson('SalesFunnel:countryConflict');
        }

        // prepare payment meta
        $metaData['newsletters_subscribe'] = (bool)filter_input(INPUT_POST, 'newsletters_subscribe');

        foreach ($this->getHttpRequest()->getPost('payment_metadata') ?? [] as $key => $submittedMeta) {
            if ($submittedMeta !== "") {
                $metaData[$key] = $submittedMeta;
            }
        }

        // custom subscription start time
        if ($this->request->getPost('subscription_starts_at')) {
            $subscriptionStartAt = DateTime::from($this->request->getPost('subscription_starts_at'));
        }

        $trackerParams = [];
        /** @var TrackerDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'sales_funnel.dataprovider.tracker',
            TrackerDataProviderInterface::class,
        );
        foreach ($providers as $provider) {
            $trackerParams[] = $provider->provide();
        }
        $trackerParams = array_merge([], ...$trackerParams);

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $paymentGateway,
            user: $user,
            paymentItemContainer: $paymentItemContainer,
            referer: $referer,
            subscriptionStartAt: $subscriptionStartAt ?? null,
            additionalAmount: $additionalAmount,
            additionalType: $additionalType,
            address: $address,
            metaData: array_merge($metaData, $trackerParams),
            paymentCountry: $resolvedCountry?->country,
            paymentCountryResolutionReason: $resolvedCountry?->getReasonValue(),
        );

        $this->paymentsRepository->update($payment, ['sales_funnel_id' => $funnel->id]);

        /** @var SalesFunnelPaymentFormDataProviderInterface[] $providers */
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
                array_merge($eventParams, $trackerParams),
            ),
        );

        if ($this->canUserUseStoredCard($user, $payment)) {
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

    private function validateFunnelLimitPerUserCount(ActiveRow $funnel, $userId)
    {
        if ($funnel->limit_per_user && $userId) {
            $paidFrom = null;
            if ($funnel->limit_per_user_for_period !== null) {
                $paidFrom = DateTime::from("-{$funnel->limit_per_user_for_period} days");
            }
            $salesFunnelUserCount = $this->salesFunnelsRepository->getAllUserSalesFunnelPurchases(
                $userId,
                $funnel->id,
                $paidFrom,
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
            'url' => $this->link($destination, $args),
        ]);
    }

    private function getPreviewMode(): ?string
    {
        $previewMode = $this->request->getQuery('preview');
        if ($previewMode === null || !$this->getUser()->isAllowed('SalesFunnel:SalesFunnelsAdmin', 'preview')) {
            return null;
        }

        return $previewMode;
    }

    private function displayCantAccessFunnel(
        ActiveRow $funnel,
        string $userAgent,
    ): void {
        $this->emitter->emit(
            new SalesFunnelEvent($funnel, $this->getUser(), SalesFunnelsStatsRepository::TYPE_NO_ACCESS, $userAgent),
        );
        $this->redirectOrSendJson('noAccess', $funnel->id);
    }

    private function canUserUseStoredCard(ActiveRow $user, ActiveRow $payment): bool
    {
        /** @var UserStoredCardDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('sales_funnel.dataprovider.payment_select_card');
        foreach ($providers as $provider) {
            if ($provider->canUserUseStoredCard($payment, $user) === false) {
                return false;
            }
        }

        return true;
    }
}
