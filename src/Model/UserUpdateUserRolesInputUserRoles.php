<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class UserUpdateUserRolesInputUserRoles extends Item
{
    /**
     * @var list<string>|null
     */
    public ?array $roles = null;
}
