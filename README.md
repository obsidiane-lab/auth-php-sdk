# Obsidiane Auth Bundle

Bundle Symfony fournissant un client HTTP prêt à l’emploi pour l’API Obsidiane Auth (cookies + CSRF stateless).

## Installation

1. Requérir le bundle (publié sur Packagist) :

```
composer require obsidiane/auth-sdk:<VERSION>
```

2. Activer le bundle si Flex ne l’ajoute pas automatiquement:

`config/bundles.php`

```
return [
    // ...
    Obsidiane\AuthBundle\ObsidianeAuthBundle::class => ['all' => true],
];
```

## Configuration

`config/packages/obsidiane_auth.yaml` (optionnel):

```
obsidiane_auth:
  base_url: '%env(string:OBSIDIANE_AUTH_BASE_URL)%'
  # Facultatif mais recommandé : doit matcher la politique ALLOWED_ORIGINS côté Auth
  origin: '%env(string:OBSIDIANE_AUTH_ORIGIN)%'
```

Le client repose sur `HttpBrowser` (BrowserKit) + `HttpClient` pour bénéficier d’un `CookieJar` complet (domaine/path/secure/expiration). Par défaut, le bundle instancie lui-même le navigateur avec les cookies activés : aucune configuration supplémentaire n’est requise (il suffit de définir `base_url`).

```php
$auth = new \Obsidiane\AuthBundle\AuthClient(
    baseUrl: 'https://auth.example.com',
    defaultHeaders: ['X-App' => 'my-service'],
    timeoutMs: 10000,
    // Origin HTTP utilisé pour la validation CSRF stateless.
    // Doit matcher la politique ALLOWED_ORIGINS ou le host du service d'auth.
    origin: 'https://app.example.com'
);
```

## Utilisation

Injection de `Obsidiane\AuthBundle\AuthClient` dans vos services/contrôleurs:

```php
public function __construct(private AuthClient $auth) {}

public function login(): Response
{
    // POST /api/auth/login
    $payload = $this->auth->auth()->login('user@example.com', 'Secret123!');
    // ...
}
```

`base_url` est requis. Le client gère automatiquement les cookies (access/refresh) et génère lui‑même un token CSRF stateless envoyé dans l’en‑tête `csrf-token` pour les mutations. Par défaut, il envoie `Accept: application/ld+json` et peut recevoir des en‑têtes supplémentaires via `$defaultHeaders` si vous instanciez manuellement le client.

### Gestion des erreurs

Toutes les erreurs HTTP de l’API lèvent désormais une `Obsidiane\AuthBundle\Exception\ApiErrorException` contenant :

- `getStatusCode()` : le status HTTP ;
- `getErrorCode()` : le code métier renvoyé par l’API (ex. `EMAIL_ALREADY_USED`) ;
- `getDetails()` : le tableau `details` renvoyé par l’API le cas échéant.

```php
use Obsidiane\AuthBundle\Exception\ApiErrorException;

try {
    $this->auth->auth()->inviteUser('invitee@example.com');
} catch (ApiErrorException $e) {
    if ($e->getErrorCode() === 'EMAIL_ALREADY_USED') {
        // ce compte est déjà actif
    }
}
```

Pour le détail des endpoints et des flows d’authentification, reportez‑vous au `README.md` du projet principal.

Pour des appels HTTP personnalisés, vous pouvez également générer un token CSRF compatible via :

```php
$csrf = $this->auth->generateCsrfToken();
// puis l'envoyer dans l'en-tête "csrf-token" de votre requête custom
```

---

## Modèles & routes API Platform

Le SDK fournit des modèles simples qui reflètent les ressources exposées par API Platform, ainsi que des helpers pour les consommer.

### Modèles

- `Obsidiane\AuthBundle\Model\Item<T>` : wrapper générique pour un item JSON‑LD (métadonnées `@id`, `@type`, `@context` + attributs métiers).
- `Obsidiane\AuthBundle\Model\Collection<T>` : wrapper générique pour une collection JSON‑LD (métadonnées + tableau d’items + `totalItems`).
- `Obsidiane\AuthBundle\Model\User` : projection métier simple (`id`, `email`, `roles`, `isEmailVerified`), optionnelle.
- `Obsidiane\AuthBundle\Model\Invite` : projection métier simple (`id`, `email`, `createdAt`, `expiresAt`, `acceptedAt`), optionnelle.

`Item` et `Collection` disposent d’une méthode `fromArray(array $data)` compatible avec les payloads JSON‑LD retournés par `/api/users/*` et `/api/invite_users*`. Elles permettent de travailler directement avec la représentation JSON‑LD exposée par l’API.

### Ressource User (Api Platform)

Les méthodes d’API suivantes renvoient désormais **le JSON‑LD brut** :

- `GET /api/users`
- `GET /api/users/{id}`
- `POST /api/users/{id}/roles` (admin, CSRF stateless généré automatiquement)

```php
// GET /api/users
/** @var array<string,mixed> $collection */
$collection = $this->auth->users()->list();

// GET /api/users/1
/** @var array<string,mixed> $userResource */
$userResource = $this->auth->users()->get(1);

// POST /api/users/{id}/roles (admin + CSRF)
/** @var array<string,mixed> $updated */
$updated = $this->auth->users()->updateRoles(1, ['ROLE_ADMIN']);
```

Si vous souhaitez projeter ces payloads en objets typés, vous pouvez utiliser `Item`/`Collection` ou vos propres DTO :

```php
use Obsidiane\AuthBundle\Model\Collection;
use Obsidiane\AuthBundle\Model\Item;

/** @var array<string,mixed> $collection */
$collection = $this->auth->users()->list();

// Collection JSON-LD
$users = Collection::fromArray($collection);

// Items JSON-LD
/** @var Item<array<string,mixed>> $first */
$first = $users->all()[0] ?? null;

if ($first !== null) {
    $attributes = $first->data(); // ['email' => '...', 'roles' => [...], ...]
}
```

### Ressource InviteUser (Api Platform)

Même logique pour `/api/invite_users` :

```php
// GET /api/invite_users
/** @var array<string,mixed> $collection */
$collection = $this->auth->invites()->list();

// GET /api/invite_users/1
/** @var array<string,mixed> $inviteResource */
$inviteResource = $this->auth->invites()->get(1);
```

Là encore, vous pouvez utiliser `Collection::fromArray()` et `Item::fromArray()` pour manipuler la structure JSON‑LD si besoin, ou projeter ces données vers vos propres modèles (par exemple `Invite` côté application).

### Helpers d’invitation (endpoints auth)

```php
// POST /api/auth/invite (admin uniquement)
$status = $this->auth->auth()->inviteUser('invitee@example.com'); // ['status' => 'INVITE_SENT', ...]
// Si le compte est déjà actif, une ApiErrorException est levée avec le code EMAIL_ALREADY_USED.

// POST /api/auth/invite/complete
$result = $this->auth->auth()->completeInvite('invitation-token', 'Secret123!');
// $result contient le payload utilisateur (similaire à l’inscription)
```
