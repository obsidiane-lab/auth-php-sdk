<?php

namespace Obsidiane\AuthBundle\Endpoint;

use Obsidiane\AuthBundle\Http\HttpClient;

/**
 * Endpoints /api/users* (ressource User exposée par API Platform).
 * Aligné sur le SDK JS :
 * - liste/détail renvoient la structure JSON-LD brute ;
 * - updateRoles retourne le payload JSON simple du contrôleur.
 */
final class UsersEndpoint
{
    private const PATH_USERS = '/api/users';
    private const CSRF_HEADER = 'csrf-token';

    public function __construct(
        private readonly HttpClient $http,
    ) {
    }

    /**
     * GET /api/users
     *
     * @return array<string,mixed> JSON-LD collection (équivalent de Collection<UserRead> côté JS)
     * @throws \JsonException
     */
    public function list(): array
    {
        return $this->http->requestJson('GET', self::PATH_USERS);
    }

    /**
     * GET /api/users/{id}
     *
     * @return array<string,mixed> JSON-LD item (équivalent de UserRead côté JS)
     * @throws \JsonException
     */
    public function get(int $id): array
    {
        return $this->http->requestJson('GET', self::PATH_USERS.'/'.$id);
    }

    /**
     * POST /api/users/{id}/roles (CSRF requis, admin)
     *
     * @param list<string> $roles
     *
     * @return array<string,mixed>
     */
    public function updateRoles(int $id, array $roles, ?string $csrfToken = null): array
    {
        $csrf = $csrfToken && $csrfToken !== '' ? $csrfToken : $this->http->generateCsrfToken();

        return $this->http->requestJson('POST', self::PATH_USERS.'/'.$id.'/roles', [
            'headers' => [self::CSRF_HEADER => $csrf],
            'json' => ['roles' => array_values(array_map(static fn ($role) => (string) $role, $roles))],
        ]);
    }

    /**
     * DELETE /api/users/{id}
     */
    public function delete(int $id): void
    {
        $this->http->requestJson('DELETE', self::PATH_USERS.'/'.$id);
    }
}
