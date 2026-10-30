<?php

declare(strict_types=1);

namespace Crm\SalesFunnelModule\Models;

use Nette\Database\Table\ActiveRow;

readonly class SalesFunnelAccessResult
{
    public function __construct(
        public ActiveRow $targetFunnel,
        public ?SalesFunnelAccessDenial $denial = null,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->denial === null;
    }
}
