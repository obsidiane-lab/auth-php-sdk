<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class AuthInviteCompleteInputInviteComplete extends Item
{
    public ?string $token = null;
    public ?string $password = null;
    public ?string $confirmPassword = null;
}
