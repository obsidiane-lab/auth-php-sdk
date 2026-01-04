<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge;

final readonly class BridgeOptions
{
    public function __construct(
        public string $baseUrl,
        public ?string $token,
        public BridgeDefaults $defaults,
        public bool $debug = false,
    ) {
        if (trim($this->baseUrl) === '') {
            throw new \InvalidArgumentException('BridgeOptions: baseUrl is required.');
        }
    }

    /**
     * @param array<string,mixed> $defaults
     */
    public static function fromConfig(string $baseUrl, ?string $token, array $defaults = [], bool $debug = false): self
    {
        $normalizedToken = $token !== null && trim($token) !== '' ? $token : null;

        return new self($baseUrl, $normalizedToken, BridgeDefaults::fromArray($defaults), $debug);
    }
}
