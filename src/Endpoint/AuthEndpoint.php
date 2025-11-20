<?php

namespace Obsidiane\AuthBundle\Endpoint;

use Obsidiane\AuthBundle\Auth\Types;
use Obsidiane\AuthBundle\Http\HttpClient;

/**
 * Regroupe les endpoints /api/auth/* (login, me, logout, register, password, invite).
 * L'interface est alignée sur le SDK JS.
 */
final class AuthEndpoint
{
    private const PATH_AUTH_ME = '/api/auth/me';
    private const PATH_AUTH_LOGIN = '/api/auth/login';
    private const PATH_AUTH_REFRESH = '/api/auth/refresh';
    private const PATH_AUTH_LOGOUT = '/api/auth/logout';
    private const PATH_AUTH_REGISTER = '/api/auth/register';
    private const PATH_AUTH_PASSWORD_FORGOT = '/api/auth/password/forgot';
    private const PATH_AUTH_PASSWORD_RESET = '/api/auth/password/reset';
    private const PATH_AUTH_INVITE = '/api/auth/invite';
    private const PATH_AUTH_INVITE_COMPLETE = '/api/auth/invite/complete';

    private const CSRF_HEADER = 'csrf-token';

    public function __construct(
        private readonly HttpClient $http,
    ) {
    }

    /**
     * GET /api/auth/me
     *
     * @return array<string,mixed>
     */
    public function me(): array
    {
        return $this->http->requestJson('GET', self::PATH_AUTH_ME);
    }

    /**
     * POST /api/auth/login (CSRF requis)
     *
     * @return array{
     *     user: array{
     *         id: int,
     *         email: string,
     *         roles: list<string>,
     *         emailVerified?: bool,
     *         lastLoginAt?: ?string
     *     },
     *     exp: int
     * }
     */
    public function login(string $email, string $password): array
    {
        $payload = $this->http->requestJson('POST', self::PATH_AUTH_LOGIN, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        return Types::normalizeLoginResponse($payload);
    }

    /**
     * POST /api/auth/refresh (CSRF facultatif)
     *
     * @return array{exp: int}
     */
    public function refresh(?string $csrf = null): array
    {
        $headers = $this->buildOptionalCsrfHeaders($csrf);

        $payload = $this->http->requestJson('POST', self::PATH_AUTH_REFRESH, [
            'headers' => $headers,
        ]);

        return [
            'exp' => isset($payload['exp']) ? (int) $payload['exp'] : 0,
        ];
    }

    /**
     * POST /api/auth/logout (CSRF requis)
     */
    public function logout(): void
    {
        $this->http->requestJson('POST', self::PATH_AUTH_LOGOUT, [
            'headers' => $this->buildRequiredCsrfHeaders(),
        ]);
    }

    /**
     * POST /api/auth/register (CSRF requis)
     *
     * @param array<string,mixed> $input
     *
     * @return array<string,mixed>
     */
    public function register(array $input): array
    {
        return $this->http->requestJson('POST', self::PATH_AUTH_REGISTER, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => $input,
        ]);
    }

    /**
     * POST /api/auth/password/forgot (CSRF requis)
     *
     * @return array<string,mixed>
     */
    public function requestPasswordReset(string $email): array
    {
        return $this->http->requestJson('POST', self::PATH_AUTH_PASSWORD_FORGOT, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => ['email' => $email],
        ]);
    }

    /**
     * POST /api/auth/password/reset (CSRF requis)
     */
    public function resetPassword(string $token, string $password): void
    {
        $this->http->requestJson('POST', self::PATH_AUTH_PASSWORD_RESET, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => [
                'token' => $token,
                'password' => $password,
            ],
        ]);
    }

    /**
     * POST /api/auth/invite (CSRF requis, admin uniquement)
     *
     * @return array<string,mixed>
     */
    public function inviteUser(string $email): array
    {
        return $this->http->requestJson('POST', self::PATH_AUTH_INVITE, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => ['email' => $email],
        ]);
    }

    /**
     * POST /api/auth/invite/complete (CSRF requis)
     *
     * @return array<string,mixed>
     */
    public function completeInvite(string $token, string $password): array
    {
        return $this->http->requestJson('POST', self::PATH_AUTH_INVITE_COMPLETE, [
            'headers' => $this->buildRequiredCsrfHeaders(),
            'json' => [
                'token' => $token,
                'password' => $password,
                'confirmPassword' => $password,
            ],
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function buildRequiredCsrfHeaders(): array
    {
        return [self::CSRF_HEADER => $this->http->generateCsrfToken()];
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
}
