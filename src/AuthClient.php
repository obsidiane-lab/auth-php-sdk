<?php

namespace Obsidiane\AuthBundle;

use Obsidiane\AuthBundle\Exception\ApiErrorException;
use Obsidiane\AuthBundle\Model\Invite as InviteModel;
use Obsidiane\AuthBundle\Model\Collection;
use Obsidiane\AuthBundle\Model\User as UserModel;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Minimal PHP SDK to interact with Obsidiane Auth endpoints.
 * - Manages a simple cookie jar to carry Set-Cookie between calls.
 * - Requires CSRF token header for state-changing endpoints.
 */
final class AuthClient
{
    private HttpBrowser $browser;
    private string $baseUrl;
    private ?string $origin;
    /** @var array<string,string> */
    private array $defaultHeaders;
    private ?int $timeoutMs;

    /**
     * @param array<string,string> $defaultHeaders
     */
    public function __construct(?HttpBrowser $browser = null, string $baseUrl = '', array $defaultHeaders = [], ?int $timeoutMs = null)
    {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }

        $clientOptions = ['cookies' => true];
        if ($timeoutMs !== null && $timeoutMs > 0) {
            $clientOptions['timeout'] = $timeoutMs / 1000;
        }

        $this->browser = $browser ?? new HttpBrowser(HttpClient::create($clientOptions));
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->origin = $this->computeOrigin($this->baseUrl);
        $this->defaultHeaders = $defaultHeaders;
        $this->timeoutMs = $timeoutMs;
    }

    private function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    /**
     * Perform a request with current cookies.
     * @param array<string,mixed> $options
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $headers = $options['headers'] ?? [];

        if ($this->origin && !isset($headers['Origin']) && !isset($headers['origin'])) {
            $headers['Origin'] = $this->origin;
        }

        $headers = array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $this->defaultHeaders,
            $headers
        );

        $server = $this->buildServerHeaders($headers);
        $content = $options['body'] ?? null;
        if (isset($options['json'])) {
            $content = json_encode($options['json'], JSON_THROW_ON_ERROR);
        }

        $this->browser->request($method, $this->url($path), [], [], $server, $content ?? '');
        return $this->browser->getResponse();
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

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function requestJson(string $method, string $path, array $options = []): array
    {
        $response = $this->request($method, $path, $options);

        return $this->decodeResponse($response);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $body = [];

        if ($status === 204) {
            return $body;
        }

        try {
            $body = $response->toArray(false);
        } catch (\Throwable) {
            $raw = $response->getContent(false);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            } else {
                $body = ['raw' => $raw];
            }
        }

        if ($status >= 400) {
            throw ApiErrorException::fromPayload($status, $body);
        }

        return $body;
    }

    private function throwForResponse(ResponseInterface $response): void
    {
        $this->decodeResponse($response);
    }

    /**
     * GET /api/auth/me
     * @return array<string,mixed>
     */
    public function me(): array
    {
        return $this->requestJson('GET', '/api/auth/me');
    }

    /**
     * Génère un token CSRF stateless compatible avec CsrfRequestValidator.
     * Peut être réutilisé par les appels personnalisés côté consommateur.
     */
    public function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * POST /api/auth/login (CSRF required)
     * @return array<string,mixed>
     */
    public function login(string $email, string $password): array
    {
        $csrf = $this->generateCsrfToken();
        return $this->requestJson('POST', '/api/auth/login', [
            'headers' => ['csrf-token' => $csrf],
            'json' => [ 'email' => $email, 'password' => $password ],
        ]);
    }

    /**
     * POST /api/auth/refresh (CSRF optional)
     * @return array<string,mixed>
     */
    public function refresh(?string $csrf = null): array
    {
        $headers = [];
        if ($csrf !== null && $csrf !== '') {
            $headers['csrf-token'] = $csrf;
        }

        return $this->requestJson('POST', '/api/auth/refresh', [
            'headers' => $headers,
        ]);
    }

    /** POST /api/auth/logout (CSRF required) */
    public function logout(): void
    {
        $csrf = $this->generateCsrfToken();
        $res = $this->request('POST', '/api/auth/logout', [
            'headers' => ['csrf-token' => $csrf],
        ]);
        $code = $res->getStatusCode();
        if ($code !== 204 && $code >= 400) {
            $this->throwForResponse($res);
        }
    }

    /**
     * POST /api/auth/register (CSRF required)
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function register(array $input): array
    {
        $csrf = $this->generateCsrfToken();
        return $this->requestJson('POST', '/api/auth/register', [
            'headers' => ['csrf-token' => $csrf],
            'json' => $input,
        ]);
    }

    /**
     * POST /api/auth/password/forgot (CSRF requis)
     * @return array<string,mixed>
     */
    public function passwordRequest(string $email): array
    {
        $csrf = $this->generateCsrfToken();
        return $this->requestJson('POST', '/api/auth/password/forgot', [
            'headers' => ['csrf-token' => $csrf],
            'json' => [ 'email' => $email ],
        ]);
    }

    /** POST /api/auth/password/reset (CSRF requis) */
    public function passwordReset(string $token, string $password): void
    {
        $csrf = $this->generateCsrfToken();
        $res = $this->request('POST', '/api/auth/password/reset', [
            'headers' => ['csrf-token' => $csrf],
            'json' => [ 'token' => $token, 'password' => $password ],
        ]);
        $code = $res->getStatusCode();
        if ($code !== 204 && $code >= 400) {
            $this->throwForResponse($res);
        }
    }

    /**
     * POST /api/auth/invite (CSRF required, admin only)
     *
     * @return array<string,mixed>
     */
    public function inviteUser(string $email): array
    {
        $csrf = $this->generateCsrfToken();
        return $this->requestJson('POST', '/api/auth/invite', [
            'headers' => ['csrf-token' => $csrf],
            'json' => ['email' => $email],
        ]);
    }

    /**
     * POST /api/auth/invite/complete (CSRF required)
     *
     * @return array<string,mixed>
     */
    public function completeInvite(string $token, string $password): array
    {
        $csrf = $this->generateCsrfToken();
        return $this->requestJson('POST', '/api/auth/invite/complete', [
            'headers' => ['csrf-token' => $csrf],
            'json' => [
                'token' => $token,
                'password' => $password,
                'confirmPassword' => $password,
            ],
        ]);
    }

    // --- ApiPlatform helpers (User & Invite resources) ---

    /**
     * GET /api/users/me (ApiResource<User>)
     */
    public function currentUserResource(): UserModel
    {
        $data = $this->requestJson('GET', '/api/users/me', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        return UserModel::fromArray($data);
    }

    /**
     * GET /api/invite_users
     *
     * @return list<InviteModel>
     */
    public function listInvites(): array
    {
        $data = $this->requestJson('GET', '/api/invite_users', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (isset($data['items']) && is_array($data['items'])) {
            $collection = Collection::fromArray($data);
            $invites = [];
            foreach ($collection->all() as $item) {
                $invites[] = InviteModel::fromArray($item->data());
            }
            return $invites;
        }

        $invites = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $invites[] = InviteModel::fromArray($row);
            }
        }

        return $invites;
    }

    /**
     * GET /api/invite_users/{id}
     */
    public function getInvite(int $id): InviteModel
    {
        $data = $this->requestJson('GET', '/api/invite_users/'.$id, [
            'headers' => ['Accept' => 'application/json'],
        ]);

        return InviteModel::fromArray($data);
    }
}
