<?php

namespace Obsidiane\AuthBundle;

use Obsidiane\AuthBundle\Exception\ApiErrorException;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\BrowserKit\Response as BrowserResponse;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Low-level HTTP client used by the SDK.
 * Centralises cookies, headers and JSON decoding.
 *
 * @internal
 */
final class ApiClient
{
    private HttpBrowser $browser;
    private string $baseUrl;
    private ?string $origin;
    /**
     * @var array<string,string>
     */
    private array $defaultHeaders;

    /**
     * @param array<string,string> $defaultHeaders
     */
    public function __construct(string $baseUrl, array $defaultHeaders = [], ?int $timeoutMs = null)
    {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }

        $clientOptions = [];
        if ($timeoutMs !== null && $timeoutMs > 0) {
            $clientOptions['timeout'] = $timeoutMs / 1000;
        }

        $this->browser = new HttpBrowser(HttpClient::create($clientOptions));
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->origin = $this->computeOrigin($this->baseUrl);
        $this->defaultHeaders = $defaultHeaders;
    }

    private function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    /**
     * Perform a request with current cookies and return the raw response.
     *
     * @param array<string,mixed> $options
     */
    private function send(string $method, string $path, array $options = []): BrowserResponse
    {
        $headers = $this->normalizeHeaders($options['headers'] ?? []);

        if ($this->origin && !isset($headers['Origin']) && !isset($headers['origin'])) {
            $headers['Origin'] = $this->origin;
        }

        $headers = array_merge(
            [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
            $this->defaultHeaders,
            $headers
        );

        $server = $this->buildServerHeaders($headers);
        $content = $options['body'] ?? null;
        if (isset($options['json'])) {
            $content = json_encode($options['json'], JSON_THROW_ON_ERROR);
        }

        $this->browser->request($method, $this->url($path), [], [], $server, $content ?? '');
        /** @var BrowserResponse $response */
        $response = $this->browser->getResponse();

        return $response;
    }

    /**
     * Perform a JSON request and decode the response.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function requestJson(string $method, string $path, array $options = []): array
    {
        $response = $this->send($method, $path, $options);

        return $this->decodeResponse($response);
    }

    /**
     * @return array<string,mixed>
     */
    public function decodeResponse(BrowserResponse $response): array
    {
        $status = $this->statusCode($response);
        $body = [];

        if ($status === 204) {
            return $body;
        }

        try {
            $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $body = is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            $raw = $response->getContent();
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : ['raw' => $raw];
        }

        if ($status >= 400) {
            throw ApiErrorException::fromPayload($status, $body);
        }

        return $body;
    }

    public function statusCode(BrowserResponse $response): int
    {
        if (method_exists($response, 'getStatusCode')) {
            return (int) $response->getStatusCode();
        }
        if (method_exists($response, 'getStatus')) {
            return (int) $response->getStatus();
        }

        return 0;
    }

    /**
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    private function buildServerHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            if ($key === 'CONTENT_TYPE') {
                $server['CONTENT_TYPE'] = $value;
            } elseif ($key === 'ACCEPT') {
                $server['HTTP_ACCEPT'] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        return $server;
    }

    /**
     * @param array<string,string|int|float> $headers
     * @return array<string,string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[$name] = (string) $value;
        }

        return $normalized;
    }

    private function computeOrigin(string $baseUrl): ?string
    {
        if ($baseUrl === '') {
            return null;
        }

        $parts = parse_url($baseUrl);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $port);
    }
}
