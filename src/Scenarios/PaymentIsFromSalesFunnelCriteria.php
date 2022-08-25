<?php

namespace Crm\SalesFunnelModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentIsFromSalesFunnelCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'is-from-sales-funnel';

    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        if ($values->selection) {
            $selection->where('payments.sales_funnel_id IS NOT NULL');
        } else {
            $selection->where('payments.sales_funnel_id IS NULL');
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('sales_funnel.admin.scenarios.criteria.is_from_sales_funnel_label');
    }
}
