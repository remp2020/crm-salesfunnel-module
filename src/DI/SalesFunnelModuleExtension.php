<?php

namespace Crm\SalesFunnelModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class SalesFunnelModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    const PARAM_FUNNEL_ROUTES = 'funnel_routes';
    const PARAM_LIMIT_PER_USER_FOR_PERIOD_OPTIONS = 'limit_per_user_for_period_options';

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            self::PARAM_FUNNEL_ROUTES => Expect::bool(true)->dynamic(),
            self::PARAM_LIMIT_PER_USER_FOR_PERIOD_OPTIONS => Expect::listOf('int')->default([31, 93, 183, 365]),
        ]);
    }

    public function loadConfiguration()
    {
        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services'],
        );
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();

        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['SalesFunnel' => 'Crm\SalesFunnelModule\Presenters\*Presenter']]);

        $builder->getDefinitionByType(Config::class)
            ->addSetup('setFunnelRoutes', [$this->config->{self::PARAM_FUNNEL_ROUTES}])
            ->addSetup('setLimitPerUserForPeriodOptions', [
                $this->config->{self::PARAM_LIMIT_PER_USER_FOR_PERIOD_OPTIONS},
            ]);
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources(): array
    {
        return [__DIR__ . '/../lang/'];
    }
}
