<?php

namespace Crm\SalesFunnelModule\DataProviders;

use Crm\ApplicationModule\Repositories\SnippetsRepository;
use Crm\ApplicationModule\Twig\Extensions\ContributteTranslationExtension;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * @property SnippetsRepository $snippetsRepository
 * @property User $user
 */
trait SalesFunnelTwigSnippetLoaderTrait
{
    /**
     * Renders list of snippets and returns array of html strings.
     *
     * @param ActiveRow $salesFunnel
     * @param array $snippets array of snippet identifiers to load
     *  snippets will be as (for example):
     *      snippet identifier `header` will be accessible as `snippetHeader`
     *      snippet identifier `one-stop`shop` as `snippetOneStopShop`
     *
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function loadSnippets(ActiveRow $salesFunnel, array $snippets): array
    {
        $user = $this->user->isLoggedIn() ? $this->usersRepository->find($this->user->getId()) : null;
        $oneStopShopData = $this->oneStopShop->getFrontendData();

        $result = [];
        foreach ($snippets as $identifier) {
            $snippetNameInFunnel = 'snippet' . self::dashesToCamelCase($identifier);

            $snippet = $this->snippetsRepository->loadByIdentifier($identifier);
            if ($snippet) {
                $loader = new ArrayLoader([
                    'snippet' => $snippet->html,
                ]);
                $twig = new Environment($loader);
                $twig->addExtension(new ContributteTranslationExtension($this->translator));
                $template = $twig->render('snippet', [
                    'user' => $user,
                    'funnel' => $salesFunnel,
                    'oneStopShop' => $oneStopShopData,
                ]);
                $result[$snippetNameInFunnel] = $template;
            }
        }

        return $result;
    }

    private static function dashesToCamelCase(string $string): string
    {
        return str_replace('-', '', ucwords($string, '-'));
    }
}
