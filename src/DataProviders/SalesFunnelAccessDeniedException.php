<?php
declare(strict_types=1);

namespace Crm\SalesFunnelModule\DataProviders;

use Exception;

class SalesFunnelAccessDeniedException extends Exception
{
    public function __construct(private readonly string $redirectUrl)
    {
        parent::__construct("Sales funnel access denied, redirecting to [{$redirectUrl}].");
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }
}
