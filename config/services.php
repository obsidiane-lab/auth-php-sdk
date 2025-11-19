<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Obsidiane\AuthBundle\AuthClient;

return static function (ContainerConfigurator $config): void {
    $services = $config->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Main AuthClient service
    $services->set(AuthClient::class)
        ->args([
            param('obsidiane_auth.base_url'),
            [],
            null,
            param('obsidiane_auth.origin'),
        ]);
};
