<?php

namespace Obsidiane\AuthBundle\Auth;

/**
 * Modèles simples pour typer les réponses Auth.
 * Ils reflètent le SDK JS (User / UserRead, etc.) mais restent optionnels :
 * l'API sous-jacente renvoie toujours des tableaux associatifs.
 */
final class Types
{
    /**
     * @param array<string,mixed> $payload
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
    public static function normalizeLoginResponse(array $payload): array
    {
        $user = $payload['user'] ?? [];
        $roles = [];

        if (isset($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                $roles[] = (string) $role;
            }
        }

        $emailVerifiedRaw = $user['emailVerified'] ?? $user['isEmailVerified'] ?? null;

        return [
            'user' => [
                'id' => isset($user['id']) ? (int) $user['id'] : 0,
                'email' => (string) ($user['email'] ?? ''),
                'roles' => $roles,
                'lastLoginAt' => isset($user['lastLoginAt']) ? (string) $user['lastLoginAt'] : null,
                'emailVerified' => $emailVerifiedRaw === null ? null : (bool) $emailVerifiedRaw,
            ],
            'exp' => isset($payload['exp']) ? (int) $payload['exp'] : 0,
        ];
    }
}
