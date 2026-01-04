# Obsidiane Auth SDK for PHP

SDK PHP orienté API pour consommer Obsidiane Auth avec une logique proche du SDK JS (bridge + facades). Authentification via Bearer token fixe, pas de cookies.

## Features

✅ **Bridge API-first** (get/post/patch/put/delete + request)
✅ **Facades ressources** avec modèles hydratés via Symfony Serializer
✅ **JSON-LD par défaut** (`Accept: application/ld+json`)
✅ **Content-Type auto** (`POST/PUT: application/ld+json`, `PATCH: application/merge-patch+json`)
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
    timeout_ms: 10000
  debug: false
```

**.env**
```bash
OBSIDIANE_AUTH_BASE_URL=https://auth.example.com
OBSIDIANE_AUTH_TOKEN=your-static-bearer-token
```

Le `token` est optionnel : s'il est absent ou vide, aucun header `Authorization` n'est ajouté (utile pour les endpoints publics).

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

## Hydratation des modèles

Les facades utilisent `Symfony\Component\Serializer\SerializerInterface::denormalize()`.
Les modèles du SDK exposent `@id` via la propriété `$iri`.
Si vous utilisez vos propres modèles, mappez `@id` avec `#[SerializedName('@id')]`.

## API publique

- `BridgeFacade` : appels HTTP bas niveau
- `FacadeFactory` : création de `ResourceFacade<T>`
- `ResourceFacade<T>` : CRUD + collections hydratées
- `Collection<T>` : items + metadata JSON-LD

## Paramètres `defaults`

- `headers`: headers appliqués à toutes les requêtes (surchargés par appel)
- `timeout_ms`: timeout en millisecondes
