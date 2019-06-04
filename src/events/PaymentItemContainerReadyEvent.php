<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use League\Event\AbstractEvent;

/**
 * PaymentItemContainerReadyEvent is emitted after PaymentItemContainer was initialized
 *
 * PaymentItemContainer should be initialized and filled with base payment items.
 * All handlers can add, update or remove PaymentItems before payment is created.
 */
class PaymentItemContainerReadyEvent extends AbstractEvent
{
    private $paymentData;

    private $paymentItemContainer;

    public function __construct(PaymentItemContainer &$paymentItemContainer, ?array $paymentData = null)
    {
        $this->paymentItemContainer = $paymentItemContainer;
        $this->paymentData = $paymentData;
    }

    public function getPaymentItemContainer(): PaymentItemContainer
    {
        return $this->paymentItemContainer;
    }

    public function getPaymentData(): ?array
    {
        return $this->paymentData;
    }
}
