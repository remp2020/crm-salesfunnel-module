<?php

namespace Crm\SalesFunnelModule\Model;

class PaymentCompleteRedirectManager
{
    private $resolvers = [];

    /**
     * registerRedirectResolver registers implementation of PaymentCompleteRedirectResolver to conditionally handle
     * payment's success page.
     *
     * @param PaymentCompleteRedirectResolver $resolver
     * @param int $priority affecting order of execution of resolvers - higher priority executes earlier
     */
    public function registerRedirectResolver(PaymentCompleteRedirectResolver $resolver, $priority = 100)
    {
        if (isset($this->resolvers[$priority])) {
            do {
                $priority++;
            } while (isset($this->resolvers[$priority]));
        }
        $this->resolvers[$priority] = $resolver;
    }

    /**
     * getResolvers returns list of PaymentCompleteRedirectResolver implementations order by priority specified during
     * implementation registration.
     *
     * @return PaymentCompleteRedirectResolver[]
     */
    public function getResolvers()
    {
        ksort($this->resolvers, SORT_NUMERIC | SORT_DESC);
        return $this->resolvers;
    }
}
