<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge\Http;

use Obsidiane\AuthBundle\Bridge\BridgeOptions;
use Obsidiane\AuthBundle\Exception\ApiErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BridgeHttpClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly BridgeOptions $options,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string,mixed> $options
     */
    public function request(string $method, string $url, array $options = []): mixed
    {
        $responseType = isset($options['responseType']) ? (string) $options['responseType'] : null;
        $timeoutMs = isset($options['timeoutMs']) ? (int) $options['timeoutMs'] : null;

        $headers = $this->buildHeaders(
            isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [],
            $method
        );

        $body = $options['body'] ?? null;
        if (array_key_exists('json', $options)) {
            $body = json_encode($options['json'], JSON_THROW_ON_ERROR);
        }

        $clientOptions = [
            'headers' => $headers,
        ];

        if ($body !== null) {
            $clientOptions['body'] = $body;
        }

        $effectiveTimeout = $timeoutMs ?? $this->options->defaults->timeoutMs;
        if ($effectiveTimeout !== null && $effectiveTimeout > 0) {
            $clientOptions['timeout'] = $effectiveTimeout / 1000;
        }

        $this->logDebug('[Bridge] request', [
            'method' => $method,
            'url' => $url,
        ]);

        $response = $this->http->request($method, $url, $clientOptions);
        $statusCode = $response->getStatusCode();
        $raw = $response->getContent(false);

        if ($responseType === 'text') {
            if ($statusCode >= 400) {
                throw ApiErrorException::fromPayload($statusCode, ['raw' => $raw]);
            }

            return $raw;
        }

        $payload = $this->decodeJson($raw);

        if ($statusCode >= 400) {
            throw ApiErrorException::fromPayload($statusCode, $payload);
        }

        $this->logDebug('[Bridge] response', [
            'method' => $method,
            'url' => $url,
            'status' => $statusCode,
        ]);

        return $payload;
    }

    /**
     * @param array<string,string> $headers
     *
     * @return array<string,string>
     */
    private function buildHeaders(array $headers, string $method): array
    {
        $merged = array_merge($this->options->defaults->headers, $headers);

        if (!$this->hasHeader($merged, 'Accept')) {
            $merged['Accept'] = 'application/ld+json';
        }

        if ($this->options->token !== null && !$this->hasHeader($merged, 'Authorization')) {
            $merged['Authorization'] = 'Bearer '.$this->options->token;
        }

        if ($this->isBodyMethod($method) && !$this->hasHeader($merged, 'Content-Type')) {
            $resolvedContentType = $this->resolveContentType($method);
            if ($resolvedContentType !== null) {
                $merged['Content-Type'] = $resolvedContentType;
            }
        }

        return $merged;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : ['raw' => $raw];
        }
    }

    /**
     * @param array<string,string> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        $needle = strtolower($name);
        foreach ($headers as $headerName => $_) {
            if (strtolower($headerName) === $needle) {
                return true;
            }
        }
        return false;
    }

    private function isBodyMethod(string $method): bool
    {
        $normalized = strtoupper($method);
        return $normalized === 'POST' || $normalized === 'PUT' || $normalized === 'PATCH';
    }

    private function resolveContentType(string $method): ?string
    {
        $normalized = strtoupper($method);
        if ($normalized === 'PATCH') {
            return 'application/merge-patch+json';
        }
        if ($normalized === 'POST' || $normalized === 'PUT') {
            return 'application/ld+json';
        }
        return null;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (!$this->options->debug) {
            return;
        }

        $this->logger?->debug($message, $context);
    }
}
