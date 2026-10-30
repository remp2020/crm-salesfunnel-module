<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Models;

readonly class SalesFunnelAccessDenial
{
    public const string REASON_REDIRECT_LOOP = 'redirect_loop';
    public const string REASON_INACTIVE = 'inactive';
    public const string REASON_ONLY_NOT_LOGGED = 'only_not_logged';
    public const string REASON_PURCHASE_LIMIT = 'purchase_limit';
    public const string REASON_SEGMENT = 'segment';
    public const string REASON_LIMIT_PER_USER = 'limit_per_user';
    public const string REASON_ACCESS_DENIED = 'access_denied';
    public const string REASON_NO_SUBSCRIPTION_TYPES = 'no_subscription_types';

    public function __construct(
        public string $reason,
        public ?string $redirectUrl = null,
    ) {
    }
}
