<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge;

/**
 * @template T of object
 */
final readonly class Collection
{
    /**
     * @param list<T> $items
     * @param string|array<mixed>|null $type
     * @param array<string,mixed>|null $context
     * @param array<string,mixed>|null $view
     * @param array<string,mixed>|null $search
     */
    public function __construct(
        public array $items,
        public ?int $totalItems = null,
        public ?string $id = null,
        public string|array|null $type = null,
        public ?array $context = null,
        public ?array $view = null,
        public ?array $search = null,
    ) {
    }
}
