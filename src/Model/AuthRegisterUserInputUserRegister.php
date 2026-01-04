<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class AuthRegisterUserInputUserRegister extends Item
{
    public ?string $email = null;
    public ?string $password = null;
}
