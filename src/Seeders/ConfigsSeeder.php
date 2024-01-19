<?php

namespace Crm\SalesFunnelModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Repositories\ConfigCategoriesRepository;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsTrait;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    use ConfigsTrait;

    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $category = $this->getCategory(
            $output,
            'sales_funnel.config.category',
            'far fa-window-maximize',
            100
        );

        $this->addConfig(
            $output,
            $category,
            'default_sales_funnel_url_key',
            ApplicationConfig::TYPE_STRING,
            'sales_funnel.config.default_sales_funnel_url_key.name',
            'sales_funnel.config.default_sales_funnel_url_key.description',
            'sample',
            900
        );

        $this->addConfig(
            $output,
            $category,
            'sales_funnel_header_block',
            ApplicationConfig::TYPE_HTML,
            'sales_funnel.config.sales_funnel_header_block.name',
            'sales_funnel.config.sales_funnel_header_block.description',
            null,
            910
        );
    }
}
