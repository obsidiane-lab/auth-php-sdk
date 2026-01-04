<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge\Http;

final readonly class HttpRequestConfig
{
    /**
     * @param array<string,scalar|array<scalar>|null> $query
     * @param array<string,string> $headers
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $query = [],
        public mixed $body = null,
        public array $headers = [],
        public ?string $responseType = null,
        public ?int $timeoutMs = null,
    ) {
    }
}
