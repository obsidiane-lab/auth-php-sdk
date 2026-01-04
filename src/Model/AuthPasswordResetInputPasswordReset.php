<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class AuthPasswordResetInputPasswordReset extends Item
{
    public ?string $token = null;
    public ?string $password = null;
}
