<?php
declare(strict_types=1);

namespace Crm\SalesFunnelModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\SalesFunnelModule\Repositories\SalesFunnelTagsRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Random;

class PaymentIsFromTagSalesFunnelCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'payment-is-from-tag-sales-funnel';

    public function __construct(
        private readonly SalesFunnelTagsRepository $salesFunnelTagsRepository,
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        $tags = $this->salesFunnelTagsRepository->tagsSortedByOccurrences();

        return [
            new StringLabeledArrayParam(self::KEY, $this->label(), $tags),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        if (empty($values->selection)) {
            throw new Exception("Empty selection is not allowed for " . self::KEY);
        }

        $suffix = Random::generate();
        $tagsAlias = "sales_funnel_tags_{$suffix}";

        $selection->alias("sales_funnel:sales_funnel_tags", $tagsAlias);
        $selection->where("{$tagsAlias}.tag IN (?)", $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('sales_funnel.admin.scenarios.criteria.payment_is_from_tag_sales_funnel_label');
    }
}
