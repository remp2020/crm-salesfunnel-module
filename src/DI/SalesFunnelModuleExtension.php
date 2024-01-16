<?php

namespace Crm\SalesFunnelModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;

class SalesFunnelModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    const PARAM_FUNNEL_ROUTES = 'funnel_routes';

    private $defaults = [
        self::PARAM_FUNNEL_ROUTES => true,
    ];

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        // set default values if user didn't define them
        $this->config = $this->validateConfig($this->defaults);

        // set extension parameters for use in config
        $builder->parameters['funnel_routes'] = $this->config['funnel_routes'];

        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        // configure API client
        $builder->getDefinitionByType(Config::class)
            ->addSetup('setFunnelRoutes', [$this->config[self::PARAM_FUNNEL_ROUTES]]);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['SalesFunnel' => 'Crm\SalesFunnelModule\Presenters\*Presenter']]);
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
