<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

final class InvitePreview extends Item
{
    public ?string $token = null;
    public ?string $email = null;
    public ?bool $accepted = null;
    public ?bool $expired = null;
}
