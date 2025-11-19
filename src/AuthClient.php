<?php

namespace Obsidiane\AuthBundle;

use Obsidiane\AuthBundle\Model\Invite as InviteModel;
use Obsidiane\AuthBundle\Model\User as UserModel;

/**
 * Minimal PHP SDK to interact with Obsidiane Auth endpoints.
 * - Manages a simple cookie jar to carry Set-Cookie between calls.
 * - Requires CSRF token header for state-changing endpoints.
 */
final class AuthClient
{
    private const CSRF_HEADER = 'csrf-token';

    private const PATH_AUTH_ME = '/api/auth/me';
    private const PATH_AUTH_LOGIN = '/api/auth/login';
    private const PATH_AUTH_REFRESH = '/api/auth/refresh';
    private const PATH_AUTH_LOGOUT = '/api/auth/logout';
    private const PATH_AUTH_REGISTER = '/api/auth/register';
    private const PATH_AUTH_PASSWORD_FORGOT = '/api/auth/password/forgot';
    private const PATH_AUTH_PASSWORD_RESET = '/api/auth/password/reset';
    private const PATH_AUTH_INVITE = '/api/auth/invite';
    private const PATH_AUTH_INVITE_COMPLETE = '/api/auth/invite/complete';
    private const PATH_USERS = '/api/users';
    private const PATH_INVITE_USERS = '/api/invite_users';

    private ApiClient $api;

    /**
     * @param array<string,string> $defaultHeaders
     */
    public function __construct(string $baseUrl = '', array $defaultHeaders = [], ?int $timeoutMs = null)
    {
        $this->api = new ApiClient($baseUrl, $defaultHeaders, $timeoutMs);
    }

    /**
     * GET a JSON-LD resource.
     *
     * @return array<string,mixed>
     */
    private function getJsonLd(string $path): array
    {
        return $this->api->requestJson('GET', $path);
    }

    /**
     * GET a JSON-LD collection and map each item.
     *
     * @template T
     * @param callable(array<string,mixed>):T $mapper
     * @return list<T>
     */
    private function getJsonLdCollection(string $path, callable $mapper): array
    {
        $data = $this->getJsonLd($path);

        $collection = Collection::fromArray($data);
        $items = [];
        foreach ($collection->all() as $item) {
            $items[] = $mapper($item->data());
        }

        return $items;
    }

    /**
     * @return array<string,string>
     */
    private function buildRequiredCsrfHeaders(): array
    {
        return [self::CSRF_HEADER => $this->generateCsrfToken()];
    }

    /**
     * @return array<string,string>
     */
    private function buildOptionalCsrfHeaders(?string $csrf): array
    {
        if ($csrf === null || $csrf === '') {
            return [];
        }

        return [self::CSRF_HEADER => $csrf];
    }

    /**
     * GET /api/auth/me
     * @return array<string,mixed>
     */
    public function me(): array
    {
        return $this->api->requestJson('GET', self::PATH_AUTH_ME);
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
     * Helper to POST JSON with a fresh CSRF token and expect a JSON payload.
     *
     * @param array<string,mixed> $json
     * @return array<string,mixed>
     */
    private function postJsonWithCsrf(string $path, array $json): array
    {
        return $this->api->requestJson('POST', $path, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => $json,
        ]);
    }

    /**
     * Helper to POST (optionally JSON) with CSRF and expect no content (204).
     *
     * @param array<string,mixed> $json
     */
    private function postWithCsrfExpectNoContent(string $path, array $json = []): void
    {
        $options = [
            'headers' => $this->buildRequiredCsrfHeaders(),
        ];
        if ($json !== []) {
            $options['json'] = $json;
        }

        // Let ApiClient handle status code and errors; ignore payload.
        $this->api->requestJson('POST', $path, $options);
    }

    /**
     * POST /api/auth/login (CSRF required)
     * @return array<string,mixed>
     */
    public function login(string $email, string $password): array
    {
        return $this->postJsonWithCsrf(self::PATH_AUTH_LOGIN, [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * POST /api/auth/refresh (CSRF optional)
     * @return array<string,mixed>
     */
    public function refresh(?string $csrf = null): array
    {
        return $this->api->requestJson('POST', self::PATH_AUTH_REFRESH, [
            'headers' => $this->buildOptionalCsrfHeaders($csrf),
        ]);
    }

    /** POST /api/auth/logout (CSRF required) */
    public function logout(): void
    {
        $this->postWithCsrfExpectNoContent(self::PATH_AUTH_LOGOUT);
    }

    /**
     * POST /api/auth/register (CSRF required)
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function register(array $input): array
    {
        return $this->postJsonWithCsrf(self::PATH_AUTH_REGISTER, $input);
    }

    /**
     * POST /api/auth/password/forgot (CSRF requis)
     * @return array<string,mixed>
     */
    public function passwordRequest(string $email): array
    {
        return $this->postJsonWithCsrf(self::PATH_AUTH_PASSWORD_FORGOT, [
            'email' => $email,
        ]);
    }

    /** POST /api/auth/password/reset (CSRF requis) */
    public function passwordReset(string $token, string $password): void
    {
        $this->postWithCsrfExpectNoContent(self::PATH_AUTH_PASSWORD_RESET, [
            'token' => $token,
            'password' => $password,
        ]);
    }

    /**
     * POST /api/auth/invite (CSRF required, admin only)
     *
     * @return array<string,mixed>
     */
    public function inviteUser(string $email): array
    {
        return $this->postJsonWithCsrf(self::PATH_AUTH_INVITE, [
            'email' => $email,
        ]);
    }

    /**
     * POST /api/auth/invite/complete (CSRF required)
     *
     * @return array<string,mixed>
     */
    public function completeInvite(string $token, string $password): array
    {
        return $this->postJsonWithCsrf(self::PATH_AUTH_INVITE_COMPLETE, [
            'token' => $token,
            'password' => $password,
            'confirmPassword' => $password,
        ]);
    }

    // --- ApiPlatform helpers (User & Invite resources) ---

    /**
     * GET /api/users
     *
     * @return list<UserModel>
     */
    public function listUsers(): array
    {
        return $this->getJsonLdCollection(
            self::PATH_USERS,
            static fn (array $row): UserModel => UserModel::fromArray($row),
        );
    }

    /**
     * GET /api/users/{id}
     */
    public function getUser(int $id): UserModel
    {
        $data = $this->getJsonLd(self::PATH_USERS.'/'.$id);

        return UserModel::fromArray($data);
    }

    /**
     * GET /api/invite_users
     *
     * @return list<InviteModel>
     */
    public function listInvites(): array
    {
        return $this->getJsonLdCollection(
            self::PATH_INVITE_USERS,
            static fn (array $row): InviteModel => InviteModel::fromArray($row),
        );
    }

    /**
     * GET /api/invite_users/{id}
     */
    public function getInvite(int $id): InviteModel
    {
        $data = $this->getJsonLd(self::PATH_INVITE_USERS.'/'.$id);

        return InviteModel::fromArray($data);
    }

    /**
     * DELETE /api/users/{id}
     */
    public function deleteUser(int $id): void
    {
        $this->api->requestJson('DELETE', self::PATH_USERS.'/'.$id);
    }
}
