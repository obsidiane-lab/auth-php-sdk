<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Model;

use Symfony\Component\Serializer\Attribute\SerializedName;

class Item
{
    #[SerializedName('@id')]
    public ?string $iri = null;

    /**
     * @var string|array<mixed>|null
     */
    #[SerializedName('@type')]
    public string|array|null $type = null;

    /**
     * @var string|array<string,mixed>|null
     */
    #[SerializedName('@context')]
    public string|array|null $context = null;
}
