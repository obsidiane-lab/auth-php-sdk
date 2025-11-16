<?php

namespace Obsidiane\AuthBundle;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Minimal PHP SDK to interact with Obsidiane Auth endpoints.
 * - Manages a simple cookie jar to carry Set-Cookie between calls.
 * - Requires CSRF token header for state-changing endpoints.
 */
final class AuthClient
{
    private HttpClientInterface $http;
    private string $baseUrl;
    private CookieJar $jar;

    public function __construct(?HttpClientInterface $http = null, ?string $baseUrl = '')
    {
        $this->http = $http ?? HttpClient::create();
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->jar = new CookieJar();
    }

    private function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function updateCookies(array $headers): void
    {
        $set = [];
        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) === 'set-cookie') {
                foreach ((array) $values as $val) {
                    $set[] = (string) $val;
                }
            }
        }
        if ($set) {
            $this->jar->addFromSetCookie($set);
        }
    }

    /**
     * Perform a request with current cookies and optional CSRF header.
     * @param array<string,mixed> $options
     */
    private function request(string $method, string $path, array $options = [], ?string $csrf = null): ResponseInterface
    {
        $headers = $options['headers'] ?? [];
        if ($csrf) {
            $headers['csrf-token'] = $csrf;
        }

        $cookieHeader = $this->jar->toHeader();
        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }

        $options['headers'] = $headers + ['Content-Type' => 'application/json'];
        $response = $this->http->request($method, $this->url($path), $options);
        $this->updateCookies($response->getHeaders(false));
        return $response;
    }

    /**
     * GET /api/auth/me
     * @return array<string,mixed>
     */
    public function me(): array
    {
        $res = $this->request('GET', '/api/auth/me');
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('me_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    private function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * POST /api/login (CSRF required)
     * @return array<string,mixed>
     */
    public function login(string $email, string $password): array
    {
        $res = $this->request('POST', '/api/login', [
            'json' => [ 'email' => $email, 'password' => $password ],
        ], $this->generateCsrfToken());
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('login_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    /**
     * POST /api/token/refresh (CSRF optional)
     * @return array<string,mixed>
     */
    public function refresh(?string $csrf = null): array
    {
        $res = $this->request('POST', '/api/token/refresh', [], $csrf);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('refresh_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    /** POST /api/auth/logout (CSRF required) */
    public function logout(): void
    {
        $res = $this->request('POST', '/api/auth/logout', [], $this->generateCsrfToken());
        $code = $res->getStatusCode();
        if ($code !== 204 && $code >= 400) {
            throw new \RuntimeException('logout_failed: '.$code);
        }
    }

    /**
     * POST /api/auth/register (CSRF required)
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function register(array $input): array
    {
        $res = $this->request('POST', '/api/auth/register', [ 'json' => $input ], $this->generateCsrfToken());
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('register_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    /**
     * POST /reset-password (CSRF `password_request` requis)
     * @return array<string,mixed>
     */
    public function passwordRequest(string $email): array
    {
        $res = $this->request('POST', '/reset-password', [ 'json' => [ 'email' => $email ] ], $this->generateCsrfToken());
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('password_request_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    /** POST /reset-password/reset (CSRF `password_reset` requis) */
    public function passwordReset(string $token, string $password): void
    {
        $res = $this->request('POST', '/reset-password/reset', [ 'json' => [ 'token' => $token, 'password' => $password ] ], $this->generateCsrfToken());
        $code = $res->getStatusCode();
        if ($code !== 204 && $code >= 400) {
            throw new \RuntimeException('password_reset_failed: '.$code);
        }
    }
}
