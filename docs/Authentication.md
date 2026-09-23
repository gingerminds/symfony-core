# Authentication & admin protection

The admin is a session-based Twig application (firewall `admin`, `form_login`); the API is
stateless (firewall `api`, opaque bearer tokens). See [Installation](Installation.md#3-security)
for the `security.yaml` to copy.

## Admin login

| Method   | Path             | Route                        |
|----------|------------------|------------------------------|
| GET/POST | `/admin/login`   | `gingerminds_core_login`     |
| GET/POST | `/admin/logout`  | `gingerminds_core_logout`    |
| GET      | `/admin/`        | `gingerminds_core_dashboard` |
| GET/POST | `/admin/profile` | `gingerminds_core_profile`   |

The login POST is handled by Symfony's `form_login` authenticator (CSRF enabled), not by a
controller. Compared to the Laravel core `AuthService`:

| Laravel core | Symfony bundle |
|---|---|
| `RateLimiter` 5 attempts per `email\|ip` | firewall `login_throttling` (per username + IP, 5 attempts / 5 minutes) |
| `Auth::attempt(..., $remember)` | `remember_me` (checkbox `_remember_me`) |
| `Origin` check against `auth.authorized_domains` (skipped in `local`) | `AuthorizedDomainListener`, driven by `gingerminds_core.security.authorized_domains` — an empty list disables it (typically in dev) |
| redirect to `route('dashboard')` | `default_target_path` (`gingerminds_core_dashboard` by default) |

Users are loaded by `gingerminds_core.security.user_provider`: roles, permissions and the
contributor are fetch-joined in one query when the session is refreshed, so permission checks
never trigger extra queries.

Override the login page with `templates/bundles/GingermindsCoreBundle/security/login.html.twig`.

## Protecting routes

Protection is declarative, by URL, in `access_control`:

```yaml
access_control:
    - { path: ^/admin/login$, roles: PUBLIC_ACCESS }
    - { path: ^/admin, roles: IS_AUTHENTICATED }
```

This replaces **both** Laravel layers (the `gingerminds-core.auth` middleware and the
`EnsureAdminAreaIsAuthenticated` safety net): any route under the admin prefix is protected,
whether it comes from the bundle, another Gingerminds bundle or the project.

## API authentication (Sanctum equivalent)

```http
POST /api/login
Content-Type: application/json

{"email": "admin@example.com", "password": "..."}
```

```json
{"token": "gm_4f1c...", "token_type": "Bearer", "expires_at": null}
```

Then `Authorization: Bearer gm_4f1c...` on every call. Tokens are random, stored as a SHA-256
hash (`api_tokens` table), optionally expiring (`gingerminds_core.api.token_ttl`), and
revocable:

```http
POST /api/logout                      # revokes the current token
POST /api/logout {"revoke_all": true} # revokes every token of the user
```

Login attempts are rate limited (`gingerminds_core.api.login_throttling`, 429 when exceeded).

Every error has the same shape, `{"message": "..."}`, translated in the `security` domain
(`translations/security.{fr,en}.yaml`, keys `security.api.*`). Invalid/expired tokens are
answered by `gingerminds_core.security.api_failure_handler` (401 + `WWW-Authenticate`),
declared as `failure_handler` of the `access_token` authenticator (see
[Installation](Installation.md#3-security)). To answer in the client's language, let Symfony
read `Accept-Language`:

```yaml
framework:
    enabled_locales: ['fr', 'en']
    set_locale_from_accept_language: true
```
Issue tokens from your own code with `ApiTokenRepository::createToken($user, 'name', $expiresAt)`.

### Enriching the login response

Implement `Gingerminds\CoreBundle\Security\Api\LoginResponseEnricherInterface` (autoconfigured):

```php
final class SiteLoginEnricher implements LoginResponseEnricherInterface
{
    public function enrich(UserInterface $user, array $data): array
    {
        return [...$data, 'sites' => $this->sites->idsFor($user)];
    }
}
```

## Authorization

Authentication (who) and authorization (what) are separate: see
[User → Roles & permissions](User.md#roles--permissions) for voters and permissions.

## Health check

`/health` is public and outside both firewalls' access control: see
[Configuration](Configuration.md#health-check-route).
