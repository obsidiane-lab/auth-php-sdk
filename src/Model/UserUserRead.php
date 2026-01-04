<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class UserUserRead extends Item
{
    public ?int $id = null;
    public string $email = '';

    /**
     * @var list<string>|null
     */
    public ?array $roles = null;

    public ?bool $emailVerified = null;
    public ?string $lastLoginAt = null;
}
