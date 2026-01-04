<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class FrontendConfig extends Item
{
    public ?string $id = null;
    public ?bool $registrationEnabled = null;
    public ?int $passwordStrengthLevel = null;
    public ?string $brandingName = null;
    public ?string $frontendRedirectUrl = null;
    public ?string $environment = null;
    public ?string $themeMode = null;
    public ?string $themeColor = null;
}
