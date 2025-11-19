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
                ->scalarNode('origin')
                    ->defaultNull()
                    ->info('En-tête Origin à envoyer pour la validation CSRF stateless (doit matcher ALLOWED_ORIGINS)')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
