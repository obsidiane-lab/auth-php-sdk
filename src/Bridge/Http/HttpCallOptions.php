<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge\Http;

final readonly class HttpCallOptions
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public array $headers = [],
        public ?int $timeoutMs = null,
        public ?string $responseType = null,
    ) {
    }
}
