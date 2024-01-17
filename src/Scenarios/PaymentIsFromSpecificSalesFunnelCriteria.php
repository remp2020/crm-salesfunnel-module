<?php

namespace Crm\SalesFunnelModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentIsFromSpecificSalesFunnelCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'is-from-specific-sales-funnel';

    private $translator;

    private $salesFunnelsRepository;

    public function __construct(
        Translator $translator,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        $this->translator = $translator;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function params(): array
    {
        $options = [];
        foreach ($this->salesFunnelsRepository->all()->fetchAll() as $salesFunnel) {
            $options[$salesFunnel->id] = [
                'label' => $salesFunnel->name,
                'subtitle' => "({$salesFunnel->url_key})",
            ];
        }

        return [
            new StringLabeledArrayParam(self::KEY, $this->label(), $options),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('payments.sales_funnel_id IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('sales_funnel.admin.scenarios.criteria.is_from_specific_sales_funnel_label');
    }
}
