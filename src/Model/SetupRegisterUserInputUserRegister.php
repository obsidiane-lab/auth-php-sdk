<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class SetupRegisterUserInputUserRegister extends Item
{
    public ?string $email = null;
    public ?string $password = null;
}
