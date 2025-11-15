# Obsidiane Auth Bundle

Bundle Symfony fournissant un client HTTP prêt à l’emploi pour l’API Obsidiane Auth (cookies + tokens CSRF stateless).

## Installation

1. Requérir le bundle (publié sur Packagist) :

```
composer require obsidiane/auth-bundle:<VERSION>
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
```

## Utilisation

Injection de `Obsidiane\AuthBundle\AuthClient` dans vos services/contrôleurs:

```
public function __construct(private AuthClient $auth) {}

public function login(): Response
{
    $csrf = $this->auth->fetchCsrfToken('authenticate');
    $payload = $this->auth->login('user@example.com', 'Secret123!', $csrf);
    // ...
}
```

Le client gère automatiquement les cookies (access/refresh) et exige l’en‑tête `X-CSRF-TOKEN` (obtenu via `fetchCsrfToken()`) pour les mutations.

Pour le détail des endpoints et des flows d’authentification, reportez‑vous au `docs/USER_GUIDE.md` du projet principal.
