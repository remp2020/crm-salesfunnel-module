<?php

namespace Crm\SalesFunnelModule\Events;

use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsStatsRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class PaymentStatusChangeHandler extends AbstractListener
{
    /** @var Emitter  */
    private $emitter;

    public function __construct(
        Emitter $emitter
    ) {
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PaymentChangeStatusEvent)) {
//            throw new \Exception('unable to handle event, expected PaymentChangeStatusEvent but got other');
        }
        $payment = $event->getPayment();

        if (!$payment->sales_funnel_id) {
            return;
        }

        if ($payment->status == PaymentsRepository::STATUS_PAID) {
            $salesFunnel = $payment->sales_funnel;
            $this->emitter->emit(new SalesFunnelEvent($salesFunnel, $payment->user, SalesFunnelsStatsRepository::TYPE_OK, $payment->user_agent));
        }
    }
}
