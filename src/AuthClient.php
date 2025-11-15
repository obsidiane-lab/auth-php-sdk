<?php

namespace Obsidiane\AuthBundle;

use Symfony\Contracts\HttpClient\HttpClientInterface;
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
    private function request(string $method, string $path, array $options = [], ?string $csrf = null)
    {
        $headers = $options['headers'] ?? [];
        if ($csrf) {
            $headers['X-CSRF-TOKEN'] = $csrf;
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

    public function fetchCsrfToken(string $tokenId): string
    {
        $res = $this->request('GET', '/api/auth/csrf/'.rawurlencode($tokenId));

        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('csrf_failed: '.$res->getStatusCode());
        }

        $payload = $res->toArray(false);

        if (!isset($payload['token']) || !is_string($payload['token']) || $payload['token'] === '') {
            throw new \RuntimeException('csrf_invalid_payload');
        }

        return $payload['token'];
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

    /**
     * POST /api/login (CSRF required)
     * @return array<string,mixed>
     */
    public function login(string $email, string $password, string $csrf): array
    {
        $res = $this->request('POST', '/api/login', [
            'json' => [ 'email' => $email, 'password' => $password ],
        ], $csrf);
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
    public function logout(string $csrf): void
    {
        $res = $this->request('POST', '/api/auth/logout', [], $csrf);
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
    public function register(array $input, string $csrf): array
    {
        $res = $this->request('POST', '/api/auth/register', [ 'json' => $input ], $csrf);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('register_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    /**
     * POST /reset-password (CSRF `password_request` requis)
     * @return array<string,mixed>
     */
    public function passwordRequest(string $email, string $csrf): array
    {
        $res = $this->request('POST', '/reset-password', [ 'json' => [ 'email' => $email ] ], $csrf);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('password_request_failed: '.$res->getStatusCode());
        }
        return $res->toArray(false);
    }

    /** POST /reset-password/reset (CSRF `password_reset` requis) */
    public function passwordReset(string $token, string $password, string $csrf): void
    {
        $res = $this->request('POST', '/reset-password/reset', [ 'json' => [ 'token' => $token, 'password' => $password ] ], $csrf);
        $code = $res->getStatusCode();
        if ($code !== 204 && $code >= 400) {
            throw new \RuntimeException('password_reset_failed: '.$code);
        }
    }
}
