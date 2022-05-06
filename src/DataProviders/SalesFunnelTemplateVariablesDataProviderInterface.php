<?php

namespace Crm\SalesFunnelModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface SalesFunnelTemplateVariablesDataProviderInterface extends DataProviderInterface
{
    // variables passed to latte template
    public const TEMPLATE_VARIABLES = 'template_variables';

    public const PARAM_SALES_FUNNEL = 'sales_funnel';

    /**
     * Provider should return array having key (template_system) => variable list (variable_name => variable_value).
     * Key 'template_system' specifies template to which variables should be passed to.
     *
     * @param array $params array should contain reference to ActiveRow of sales funnel in 'sales_funnel' key
     *
     * @return array
     */
    public function provide(array $params): array;
}
