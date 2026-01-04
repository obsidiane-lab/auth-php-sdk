<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Obsidiane\AuthBundle\Bridge\BridgeFacade;
use Obsidiane\AuthBundle\Bridge\BridgeOptions;
use Obsidiane\AuthBundle\Bridge\FacadeFactory;
use Obsidiane\AuthBundle\Bridge\Http\BridgeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $config): void {
    $services = $config->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(BridgeOptions::class)
        ->factory([BridgeOptions::class, 'fromConfig'])
        ->args([
            param('obsidiane_auth.base_url'),
            param('obsidiane_auth.token'),
            param('obsidiane_auth.defaults'),
            param('obsidiane_auth.debug'),
        ]);

    $services->set(BridgeHttpClient::class)
        ->args([
            service(HttpClientInterface::class),
            service(BridgeOptions::class),
            service(\Psr\Log\LoggerInterface::class)->nullOnInvalid(),
        ]);

    $services->set(BridgeFacade::class);
    $services->set(FacadeFactory::class);
};
