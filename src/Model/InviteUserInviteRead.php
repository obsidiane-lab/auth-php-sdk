<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class InviteUserInviteRead extends Item
{
    public ?int $id = null;
    public ?string $email = null;
    public ?string $createdAt = null;
    public ?string $expiresAt = null;
    public ?string $acceptedAt = null;
}
