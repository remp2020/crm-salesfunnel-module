<?php

namespace Crm\SalesFunnelModule;

use Crm\PaymentsModule\Model\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class SalesFunnelPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    public function wantsToRedirect(?ActiveRow $payment, string $status): bool
    {
        if ($payment && in_array($status, [self::PAID, self::CANCELLED])) {
            return true;
        }
        if (in_array($status, [self::NOT_SETTLED, self::ERROR])) {
            return true;
        }

        return false;
    }

    public function redirectArgs(?ActiveRow $payment, string $status): array
    {
        if ($status === self::PAID) {
            return [
                ':SalesFunnel:SalesFunnel:success',
                [
                    'variableSymbol' => $payment->variable_symbol,
                ],
            ];
        }

        if ($status === self::NOT_SETTLED) {
            return [
                ':SalesFunnel:SalesFunnel:notSettled',
            ];
        }

        if ($status === self::CANCELLED) {
            return [
                ':SalesFunnel:SalesFunnel:cancel',
                [
                    'salesFunnelId' => $payment->sales_funnel_id,
                ],
            ];
        }

        if ($status === self::ERROR) {
            return [
                ':SalesFunnel:SalesFunnel:error',
                [
                    'payment_gateway_id' => $payment->payment_gateway_id ?? null,
                    'subscription_type_id' => $payment->subscription_type_id ?? null,
                    'subscription_type' => isset($payment->subscription_type) ? $payment->subscription_type->code : null,
                ],
            ];
        }

        throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
    }
}
