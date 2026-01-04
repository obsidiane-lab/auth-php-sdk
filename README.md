# Obsidiane Auth SDK for PHP

SDK PHP orienté API pour consommer Obsidiane Auth avec une logique proche du SDK JS (bridge + facades). Authentification via Bearer token (optionnel), pas de cookies.

## Features

✅ **Bridge API-first** (get/getCollection/post/patch/put/delete + request)
✅ **Facades ressources** avec modèles hydratés via Symfony Serializer
✅ **JSON-LD par défaut** (`Accept: application/ld+json`)
✅ **Content-Type auto** (`POST/PUT: application/ld+json`, `PATCH: application/merge-patch+json`)
✅ **Exceptions structurées** via `ApiErrorException` sur erreurs HTTP
✅ **Configuration Symfony** simple et explicite

## Installation

```bash
composer require obsidiane/auth-sdk
```

## Configuration Symfony

**config/bundles.php**
```php
return [
    // ...
    Obsidiane\AuthBundle\ObsidianeAuthBundle::class => ['all' => true],
];
```

**config/packages/obsidiane_auth.yaml**
```yaml
obsidiane_auth:
  base_url: '%env(OBSIDIANE_AUTH_BASE_URL)%'
  token: '%env(OBSIDIANE_AUTH_TOKEN)%' # optionnel
  defaults:
    headers: { }
    timeout_ms: 10000 # optionnel
  debug: false
```

**.env**
```bash
OBSIDIANE_AUTH_BASE_URL=https://auth.example.com
OBSIDIANE_AUTH_TOKEN=your-static-bearer-token
```

Le `token` est optionnel : s'il est absent ou vide, aucun header `Authorization` n'est ajouté (utile pour les endpoints publics).
`base_url` est requis et peut inclure ou non `/api` selon votre usage (adaptez vos routes en conséquence).

## Utilisation

### FacadeFactory (ressources)

```php
use Obsidiane\AuthBundle\Bridge\FacadeFactory;

final class UsersService
{
    public function __construct(
        private readonly FacadeFactory $factory,
    ) {}

    public function listUsers(): array
    {
        $users = $this->factory->create('/api/users', \App\Dto\UserRead::class);
        $result = $users->getCollection();

        return $result->items; // list<UserRead>
    }
}
```

### BridgeFacade (endpoints custom)

```php
use Obsidiane\AuthBundle\Bridge\BridgeFacade;

final class AuthService
{
    public function __construct(
        private readonly BridgeFacade $bridge,
    ) {}

    public function me(): array
    {
        return $this->bridge->get('/api/auth/me');
    }
}
```

### Requête custom avec `HttpRequestConfig`

```php
use Obsidiane\AuthBundle\Bridge\Http\HttpRequestConfig;

$req = new HttpRequestConfig(
    method: 'GET',
    url: '/api/users',
    query: ['page' => 1, 'itemsPerPage' => 20],
    headers: ['X-Request-ID' => 'req_123'],
    timeoutMs: 5000,
);

$response = $bridge->request($req);
```

## Hydratation des modèles

Les facades utilisent `Symfony\Component\Serializer\Normalizer\NormalizerInterface` et `DenormalizerInterface` pour la sérialisation/désérialisation.
Les modèles du SDK exposent `@id` via la propriété `$iri`.
Si vous utilisez vos propres modèles, mappez `@id` avec `#[SerializedName('@id')]`.

**Note technique** : Le SDK nécessite que le `SerializerInterface` implémente aussi `NormalizerInterface` et `DenormalizerInterface` (ce qui est le cas avec le Serializer standard de Symfony). Le `FacadeFactory` vérifie automatiquement ces interfaces au runtime.

## API publique

### Classes principales

- **`BridgeFacade`** : appels HTTP bas niveau (GET, GET collection, POST, PUT, PATCH, DELETE, request)
- **`FacadeFactory`** : création de `ResourceFacade<T>` avec hydratation automatique
- **`ResourceFacade<T>`** : CRUD + collections hydratées (generic type-safe)
- **`Collection<T>`** : items + metadata JSON-LD (`totalItems`, `id`, `type`, `context`, `view`, `search`)

### Modèles

Les modèles exposent les propriétés JSON-LD standard :
- `Item` : classe de base avec `$iri` (`@id`), `$type` (`@type`), `$context` (`@context`)
- Tous les modèles générés héritent de `Item` ou exposent ces propriétés

### Paramètres de configuration

#### Clés principales

- `base_url` : URL de base du service Auth (requis).
- `token` : Bearer token optionnel, ajouté si non vide.
- `debug` : active les logs debug du bridge HTTP.

#### `defaults` (dans `obsidiane_auth.yaml`)

- `headers`: headers HTTP appliqués à toutes les requêtes (peuvent être surchargés par appel)
- `timeout_ms`: timeout en millisecondes (par défaut: aucun)

#### Options par requête

Toutes les méthodes de `ResourceFacade` et `BridgeFacade` acceptent un paramètre `HttpCallOptions` optionnel :

```php
use Obsidiane\AuthBundle\Bridge\Http\HttpCallOptions;

$options = new HttpCallOptions(
    headers: ['X-Custom-Header' => 'value'],
    timeoutMs: 5000, // en ms
    responseType: 'text',
);

$users->getCollection([], $options);
```

`responseType: 'text'` retourne la réponse brute (string). Par défaut, la réponse est décodée en JSON.

## Exemples avancés

### Filtrage et pagination

```php
$users = $factory->create('/api/users', UserRead::class);

// Pagination
$result = $users->getCollection([
    'page' => 2,
    'itemsPerPage' => 10,
]);

// Filtres
$result = $users->getCollection([
    'filters' => [
        'email' => 'user@example.com',
        'role' => 'ROLE_ADMIN',
    ],
]);

// Metadata
echo $result->totalItems;  // Nombre total d'items
echo $result->id;          // IRI de la collection (@id)
```

### Headers personnalisés

```php
use Obsidiane\AuthBundle\Bridge\Http\HttpCallOptions;

$options = new HttpCallOptions(
    headers: [
        'X-Request-ID' => uniqid(),
        'Accept-Language' => 'fr-FR',
    ],
);

$user = $users->get('/api/users/1', $options);
```

### Gestion d'erreurs

```php
use Obsidiane\AuthBundle\Exception\ApiErrorException;

try {
    $user = $users->get('/api/users/999');
} catch (ApiErrorException $e) {
    // Erreur HTTP (404, 500, etc.)
    error_log($e->getMessage());
    error_log('Status code: ' . $e->getStatusCode());
    error_log('Error code: ' . $e->getErrorCode());
}
```

`ApiErrorException` expose aussi `getDetails()` et `getPayload()` pour inspecter la réponse.

## Qualité du code

Le SDK maintient une qualité de code stricte :

- ✅ **PHPStan Level 6** : Zero erreur d'analyse statique
- ✅ **Types stricts** : `declare(strict_types=1)` dans tous les fichiers
- ✅ **Generics** : `ResourceFacade<T>`, `Collection<T>` pour type-safety
- ✅ **Readonly** : Classes immuables avec `readonly`
- ✅ **PSR-12** : Standard de code PHP

## Troubleshooting

### Erreur "Serializer must implement NormalizerInterface"

Si vous obtenez cette erreur, assurez-vous que votre `SerializerInterface` est bien le Serializer standard de Symfony (pas un mock ou une implémentation custom sans normalizer).

### Timeout des requêtes

Par défaut, aucun timeout n'est appliqué. Pour en définir un :

```yaml
# config/packages/obsidiane_auth.yaml
obsidiane_auth:
  defaults:
    timeout_ms: 30000  # 30 secondes
```

Ou par requête :

```php
use Obsidiane\AuthBundle\Bridge\Http\HttpCallOptions;

$options = new HttpCallOptions(timeoutMs: 30000);
$result = $users->getCollection([], $options);
```
