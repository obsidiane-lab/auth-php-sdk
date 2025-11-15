# Obsidiane Auth Bundle

Bundle Symfony fournissant un **client HTTP prêt à l’emploi** pour l’API **Obsidiane Auth**.

Il gère pour toi :

- les appels HTTP vers le service d’authentification ;
- les cookies d’auth (access / refresh) via HttpClient ;
- la récupération des tokens CSRF stateless (`/api/auth/csrf/{id}`) ;
- les opérations courantes : `login`, `me`, `refresh`, `logout`, `register`, `passwordRequest`, `passwordReset`, `listUsers`, `getUser`.

---

## Installation

### 1. Dépendance Composer

Le bundle est publié sur Packagist :

```bash
composer require obsidiane/auth-sdk:<VERSION>
````

> Remplace `<VERSION>` par la version souhaitée (ou laisse Composer résoudre la dernière version compatible).

### 2. Activation du bundle

Si Symfony Flex ne l’enregistre pas automatiquement, ajoute-le dans `config/bundles.php` :

```php
return [
    // ...
    Obsidiane\AuthBundle\ObsidianeAuthBundle::class => ['all' => true],
];
```

---

## Configuration

Fichier optionnel `config/packages/obsidiane_auth.yaml` :

```yaml
obsidiane_auth:
  base_url: '%env(string:OBSIDIANE_AUTH_BASE_URL)%'
```

Dans ton `.env` :

```env
OBSIDIANE_AUTH_BASE_URL=https://auth.example.com
```

* `base_url` doit pointer vers la racine du service Obsidiane Auth (sans trailing slash).
* Si ton app Symfony est **sur le même domaine** que le service d’auth, tu peux laisser cette valeur vide ou la régler sur `/`.

---

## Utilisation rapide

Le client principal est `Obsidiane\AuthBundle\AuthClient`.
Tu peux l’injecter dans n’importe quel contrôleur ou service.

```php
use Obsidiane\AuthBundle\AuthClient;

final class AuthController
{
    public function __construct(
        private AuthClient $auth,
    ) {}

    public function login(): Response
    {
        // 1) Récupérer un token CSRF
        $csrf = $this->auth->fetchCsrfToken('authenticate');

        // 2) Appeler l’API de login
        $payload = $this->auth->login('user@example.com', 'Secret123!', $csrf);

        // $payload contient typiquement { user: { ... }, exp: ... }
        // Les cookies (__Secure-at, __Host-rt) sont gérés par HttpClient / le navigateur.

        // ...
    }
}
```

Le client :

* ajoute automatiquement l’en-tête `X-CSRF-TOKEN` pour les mutations si tu lui passes le token ;
* se charge de cibler les bonnes routes (`/api/login`, `/api/auth/me`, etc.) à partir de `base_url`.

---

## API du client

> Les noms exacts peuvent légèrement varier selon la version, mais l’idée globale est la suivante.

### `fetchCsrfToken(string $id): string`

Récupère un token CSRF pour une opération donnée.

```php
$token = $this->auth->fetchCsrfToken('authenticate');
```

* `id` ∈ `authenticate`, `register`, `password_request`, `password_reset`, `logout`, `initial_admin`.
* Correspond à `GET /api/auth/csrf/{id}` côté service.

---

### `login(string $email, string $password, string $csrf): array`

Effectue un login et laisse le service poser les cookies d’authentification.

```php
$csrf = $this->auth->fetchCsrfToken('authenticate');

$payload = $this->auth->login(
    'user@example.com',
    'Secret123!',
    $csrf,
);
```

* Appelle `POST /api/login` avec `X-CSRF-TOKEN`.
* Retourne la réponse JSON décodée (ex. `['user' => [...], 'exp' => ...]`).

---

### `me<T = array>(): T`

Récupère l’utilisateur courant.

```php
$user = $this->auth->me()['user'] ?? null;
```

* Appelle `GET /api/auth/me`.
* Repose sur le cookie `__Secure-at` côté client (navigateur / reverse-proxy).

---

### `listUsers(array $query = []): array`

Récupère la collection d'utilisateurs (ROLE_ADMIN requis côté API).

```php
$response = $this->auth->listUsers([
    'itemsPerPage' => 50,
]);

foreach ($response['hydra:member'] ?? [] as $user) {
    // $user est un tableau associatif (id, email, displayName, roles, etc.)
    echo $user['email'] ?? '';
}
```

* Appelle `GET /api/users` (exposé par API Platform).
* Supporte les paramètres de pagination / filtrage (ex. `itemsPerPage`, `page`, etc.).

---

### `getUser(int|string $id): array`

Récupère un utilisateur par son identifiant.

```php
$user = $this->auth->getUser(1);

echo $user['email'] ?? '';
```

* Appelle `GET /api/users/{id}`.
* Nécessite les droits appropriés côté API (`ROLE_ADMIN` ou voteur `USER_READ`).

---

### `refresh(): array`

Rafraîchit le token d’accès.

```php
$payload = $this->auth->refresh();
```

* Appelle `POST /api/token/refresh`.
* Utilise automatiquement le cookie `__Host-rt`.
* Ne nécessite pas de CSRF.

---

### `logout(string $csrf): void`

Effectue un logout complet.

```php
$csrf = $this->auth->fetchCsrfToken('logout');
$this->auth->logout($csrf);
```

* Appelle `POST /api/auth/logout` avec `X-CSRF-TOKEN`.
* Le service invalide l’access token, supprime le refresh token et expire les cookies.

---

### `register(string $email, string $password, string $displayName, string $csrf): array`

Crée un nouvel utilisateur.

```php
$csrf = $this->auth->fetchCsrfToken('register');

$payload = $this->auth->register(
    'user@example.com',
    'Secret123!',
    'John Doe',
    $csrf,
);
```

* Appelle `POST /api/auth/register`.
* Le service enverra l’email de vérification (`/verify-email`).

---

### Réinitialisation de mot de passe

Helpers pour piloter le flow `/reset-password` depuis Symfony (si besoin d’appels serveur à serveur).

#### `passwordRequest(string $email, string $csrf): void`

Déclenche un email de réinitialisation.

```php
$csrf = $this->auth->fetchCsrfToken('password_request');
$this->auth->passwordRequest('user@example.com', $csrf);
```

* Appelle `POST /reset-password` avec `{ email }`.

#### `passwordReset(string $token, string $password, string $csrf): void`

Soumet le nouveau mot de passe.

```php
$csrf = $this->auth->fetchCsrfToken('password_reset');

$this->auth->passwordReset(
    'resetTokenRecuParEmail',
    'NewSecret123!',
    $csrf,
);
```

* Appelle `POST /reset-password/reset` avec `{ token, password }`.
