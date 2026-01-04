<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('obsidiane_auth');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('base_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('URL de base du service Auth (ex: https://auth.example.com)')
                ->end()
                ->scalarNode('token')
                    ->info('Bearer token utilisé pour authentifier les requêtes.')
                    ->defaultNull()
                ->end()
                ->booleanNode('debug')
                    ->defaultFalse()
                    ->info('Active les logs debug du bridge HTTP.')
                ->end()
                ->arrayNode('defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('headers')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('timeout_ms')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
