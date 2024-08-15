<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\UsersModule\Events\UserEventInterface;
use League\Event\AbstractEvent;

/**
 * PaymentItemContainerReadyEvent is emitted after PaymentItemContainer was initialized
 *
 * PaymentItemContainer should be initialized and filled with base payment items.
 * All handlers can add, update or remove PaymentItems before payment is created.
 */
class PaymentItemContainerReadyEvent extends AbstractEvent implements UserEventInterface
{
    public function __construct(
        private PaymentItemContainer $paymentItemContainer,
        private ActiveRow $user,
        private ?array $paymentData = null,
    ) {
    }

    public function getPaymentItemContainer(): PaymentItemContainer
    {
        return $this->paymentItemContainer;
    }

    public function getPaymentData(): ?array
    {
        return $this->paymentData;
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }
}
