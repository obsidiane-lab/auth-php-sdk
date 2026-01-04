<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge;

/**
 * Default HTTP behavior applied to every request.
 */
final readonly class BridgeDefaults
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public array $headers = [],
        public ?int $timeoutMs = null,
    ) {
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $headers = [];
        if (isset($config['headers']) && is_array($config['headers'])) {
            foreach ($config['headers'] as $name => $value) {
                $headers[(string) $name] = (string) $value;
            }
        }

        $timeoutMs = isset($config['timeout_ms']) ? (int) $config['timeout_ms'] : null;

        return new self($headers, $timeoutMs);
    }
}
