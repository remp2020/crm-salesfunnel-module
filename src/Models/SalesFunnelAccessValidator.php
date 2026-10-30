<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Models;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelAccessDeniedException;
use Crm\SalesFunnelModule\DataProviders\ValidateUserFunnelAccessDataProviderInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SegmentModule\Models\SegmentException;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Security\IIdentity;
use Nette\UnexpectedValueException;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Side-effect-free sales funnel access checks shared by the frontend presenter (which maps failures to redirects)
 * and by places which only need to know whether the user would be able to access the funnel (e.g. admin widgets).
 */
class SalesFunnelAccessValidator
{
    public function __construct(
        private readonly SalesFunnelsRepository $salesFunnelsRepository,
        private readonly SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        private readonly SegmentFactoryInterface $segmentFactory,
        private readonly DataProviderManager $dataProviderManager,
        private readonly SalesFunnelRenderer $salesFunnelRenderer,
    ) {
    }

    public function isFunnelActive(ActiveRow $funnel): bool
    {
        if (!$funnel->is_active) {
            return false;
        }

        $now = new DateTime();
        if ($funnel->start_at && $funnel->start_at > $now) {
            return false;
        }
        if ($funnel->end_at && $funnel->end_at < $now) {
            return false;
        }

        return true;
    }

    public function isPurchaseLimitReached(ActiveRow $funnel): bool
    {
        $purchaseLimit = $this->salesFunnelsMetaRepository->get($funnel, 'funnel_purchase_limit');
        if (!$purchaseLimit) {
            return false;
        }

        $purchases = $this->salesFunnelsRepository->getAllSalesFunnelPurchases($funnel->id)
            ->count(':payments.id');

        return $purchases >= (int) $purchaseLimit;
    }

    public function isUserInFunnelSegment(ActiveRow $funnel, int $userId): bool
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

    public function isUserLimitReached(ActiveRow $funnel, int $userId): bool
    {
        if (!$funnel->limit_per_user) {
            return false;
        }

        $paidFrom = null;
        if ($funnel->limit_per_user_for_period !== null) {
            $paidFrom = DateTime::from("-{$funnel->limit_per_user_for_period} days");
        }
        $salesFunnelUserCount = $this->salesFunnelsRepository->getAllUserSalesFunnelPurchases(
            $userId,
            $funnel->id,
            $paidFrom,
        )->count(':payments.id');

        return $salesFunnelUserCount >= $funnel->limit_per_user;
    }

    /**
     * Runs `salesfunnel.dataprovider.validate_funnel` data providers.
     *
     * @return SalesFunnelAccessDenial|null Null if all providers allow the access.
     */
    public function validateDataProviders(ActiveRow $funnel, ActiveRow|IIdentity|null $user): ?SalesFunnelAccessDenial
    {
        /** @var ValidateUserFunnelAccessDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'salesfunnel.dataprovider.validate_funnel',
            ValidateUserFunnelAccessDataProviderInterface::class,
        );
        foreach ($providers as $provider) {
            try {
                if (!$provider->provide(['user' => $user, 'sales_funnel' => $funnel])) {
                    return new SalesFunnelAccessDenial(SalesFunnelAccessDenial::REASON_ACCESS_DENIED);
                }
            } catch (SalesFunnelAccessDeniedException $e) {
                return new SalesFunnelAccessDenial(SalesFunnelAccessDenial::REASON_ACCESS_DENIED, $e->getRedirectUrl());
            }
        }

        return null;
    }

    /**
     * Evaluates whether the logged-in user would be able to see and purchase the funnel, in the same order
     * as the frontend presenter validates it. Redirect funnels are followed to the target funnel.
     */
    public function evaluate(ActiveRow $funnel, ActiveRow $user): SalesFunnelAccessResult
    {
        $visitedFunnelIds = [];
        while ($funnel->redirect_funnel) {
            $visitedFunnelIds[$funnel->id] = true;
            $funnel = $funnel->redirect_funnel;
            if (isset($visitedFunnelIds[$funnel->id])) {
                return $this->denied($funnel, SalesFunnelAccessDenial::REASON_REDIRECT_LOOP);
            }
        }

        if (!$this->isFunnelActive($funnel)) {
            return $this->denied($funnel, SalesFunnelAccessDenial::REASON_INACTIVE);
        }
        if ($funnel->only_not_logged) {
            return $this->denied($funnel, SalesFunnelAccessDenial::REASON_ONLY_NOT_LOGGED);
        }
        if ($this->isPurchaseLimitReached($funnel)) {
            return $this->denied($funnel, SalesFunnelAccessDenial::REASON_PURCHASE_LIMIT);
        }
        try {
            $isUserInFunnelSegment = $this->isUserInFunnelSegment($funnel, $user->id);
        } catch (SegmentException | UnexpectedValueException $e) {
            // misconfigured segment shouldn't break the caller (e.g. admin page); frontend would fail with it anyway
            Debugger::log($e, Debugger::EXCEPTION);
            $isUserInFunnelSegment = false;
        }
        if (!$isUserInFunnelSegment) {
            return $this->denied($funnel, SalesFunnelAccessDenial::REASON_SEGMENT);
        }
        if ($this->isUserLimitReached($funnel, $user->id)) {
            return $this->denied($funnel, SalesFunnelAccessDenial::REASON_LIMIT_PER_USER);
        }
        $denial = $this->validateDataProviders($funnel, $user);
        if ($denial !== null) {
            return new SalesFunnelAccessResult($funnel, $denial);
        }
        if ($this->salesFunnelRenderer->getPurchasableSubscriptionTypes($funnel, $user) === []) {
            return $this->denied($funnel, SalesFunnelAccessDenial::REASON_NO_SUBSCRIPTION_TYPES);
        }

        return new SalesFunnelAccessResult($funnel);
    }

    private function denied(ActiveRow $funnel, string $reason): SalesFunnelAccessResult
    {
        return new SalesFunnelAccessResult($funnel, new SalesFunnelAccessDenial($reason));
    }
}
