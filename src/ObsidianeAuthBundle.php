<?php

namespace Obsidiane\AuthBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ObsidianeAuthBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // obsidiane_auth:
        //   base_url: 'https://auth.example.com'
        /** @var ArrayNodeDefinition $root */
        $root = $definition->rootNode();
        $children = $root->children();
        $children->scalarNode('base_url')->defaultNull();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Expose parameter from bundle config
        $container->parameters()->set('obsidiane_auth.base_url', $config['base_url'] ?? null);

        // Register services via PHP config file
        $container->import(__DIR__.'/../config/services.php');
    }
}
