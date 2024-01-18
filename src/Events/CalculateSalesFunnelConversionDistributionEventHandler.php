<?php


namespace Crm\SalesFunnelModule\Events;

use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\Models\Distribution\PaymentsCountDistribution;
use Crm\SalesFunnelModule\Models\Distribution\PaymentsSumDistribution;
use Crm\SalesFunnelModule\Models\Distribution\SubscriptionDaysDistribution;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class CalculateSalesFunnelConversionDistributionEventHandler extends AbstractListener
{
    private $subscriptionDaysDistribution;

    private $paymentsCountDistribution;

    private $paymentsSumDistribution;

    public function __construct(
        PaymentsCountDistribution $paymentsCountDistribution,
        PaymentsSumDistribution $paymentsSumDistribution,
        SubscriptionDaysDistribution $subscriptionDaysDistribution
    ) {
        $this->subscriptionDaysDistribution = $subscriptionDaysDistribution;
        $this->paymentsCountDistribution = $paymentsCountDistribution;
        $this->paymentsSumDistribution = $paymentsSumDistribution;
    }

    public function handle(EventInterface $event)
    {
        if ($event instanceof PaymentChangeStatusEvent) {
            // calculate single user distribution after the payment
            $payment = $event->getPayment();
            if ($payment->status !== PaymentsRepository::STATUS_PAID) {
                return;
            }
            if (!$payment->sales_funnel) {
                return;
            }

            $this->subscriptionDaysDistribution->calculateUserDistribution($payment->sales_funnel, $payment->user);
            $this->paymentsCountDistribution->calculateUserDistribution($payment->sales_funnel, $payment->user);
            $this->paymentsSumDistribution->calculateUserDistribution($payment->sales_funnel, $payment->user);
            return;
        }

        if (!$event instanceof CalculateSalesFunnelConversionDistributionEvent) {
            throw new \Exception("invalid type of event received: " . get_class($event));
        }

        $salesFunnel = $event->getSalesFunnel();

        $this->subscriptionDaysDistribution->calculateDistribution($salesFunnel);
        $this->paymentsCountDistribution->calculateDistribution($salesFunnel);
        $this->paymentsSumDistribution->calculateDistribution($salesFunnel);
    }
}
