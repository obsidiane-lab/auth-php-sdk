<?php

namespace Obsidiane\AuthBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ParametersConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\DefaultsConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\DefinitionConfigurator;

class ObsidianeAuthBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        // obsidiane_auth:
        //   base_url: 'https://auth.example.com'
        $root = $definition->rootNode();
        $root
            ->children()
                ->scalarNode('base_url')->defaultNull()->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Expose parameter from bundle config
        $container->parameters()->set('obsidiane_auth.base_url', $config['base_url'] ?? null);

        // Register services via PHP config file
        $container->import(__DIR__.'/../config/services.php');
    }
}

