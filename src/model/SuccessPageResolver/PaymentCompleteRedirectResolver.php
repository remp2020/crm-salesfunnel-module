<?php

namespace Crm\SalesFunnelModule\Model;

use Nette\Database\Table\ActiveRow;

interface PaymentCompleteRedirectResolver
{
    const PAID = 'paid';

    const NOT_SETTLED = 'not_settled';

    const CANCELLED = 'cancelled';

    const ERROR = 'error';

    /**
     * shouldRedirect decides whether the implementation should be used to redirect user after successful payment to
     * custom location based on arbitrary condition using $payment instance.
     *
     * @param ActiveRow $payment instance of completed payment
     * @param string $status completion status of payment; use one of the constants provided by PaymentCompleteRedirectResolver
     * @return bool
     */
    public function wantsToRedirect(ActiveRow $payment, string $status): bool;

    /**
     * redirectArgs return array of arguments to be used in presenter's ->redirect() method.
     *
     * Expected usage is to trust the result and use it directly as parameters (example in presenter's context):
     *   $this->redirect(...$resolver->redirectArgs($payment));
     *
     * @param ActiveRow $payment instance of completed payment
     * * @param string $status completion status of payment; use one of the constants provided by PaymentCompleteRedirectResolver
     * @return array
     */
    public function redirectArgs(ActiveRow $payment, string $status): array;
}
