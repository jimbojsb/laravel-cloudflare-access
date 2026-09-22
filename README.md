# Cloudflare Access for Laravel

Authentication via Cloudflare Access JWT validation for Laravel.

## Requirements

- PHP 8.3+
- Laravel 11.0+ or 12.0+

## Installation

```bash
composer require jimbojsb/cloudflare-access-laravel
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=cloudflare-access-config
```

### Publish Migration (Optional)

```bash
php artisan vendor:publish --tag=cloudflare-access-migrations
php artisan migrate
```

The migration creates a `users` table with `id`, `name`, `email`, `groups` (nullable json), and timestamps.

## Configuration

Add to your `.env`:

```env
CLOUDFLARE_ACCESS_SUBDOMAIN=yourcompany
CLOUDFLARE_ACCESS_AUDIENCE=your-application-audience-tag
CLOUDFLARE_ACCESS_POPULATE_GROUPS=false
```

- `CLOUDFLARE_ACCESS_SUBDOMAIN`: Your team domain subdomain (e.g., if your domain is `yourcompany.cloudflareaccess.com`, use `yourcompany`)
- `CLOUDFLARE_ACCESS_AUDIENCE`: The Application Audience (AUD) Tag from Cloudflare Zero Trust dashboard
- `CLOUDFLARE_ACCESS_POPULATE_GROUPS`: Set to `true` to sync groups from Cloudflare Access JWT to the user model (default: `false`)

### User Model

Your User model needs `name`, `email`, and `groups` columns. Update `config/cloudflare-access.php` if using a different model:

```php
'user_model' => App\Models\User::class,
```

Ensure your model casts groups as an array:

```php
protected $casts = [
    'groups' => 'array',
];
```

## Usage

### Add Login Route

Register the login route in your `routes/web.php`:

```php
use Jimbojsb\CloudflareAccess\Http\Controllers\LoginController;

Route::get('/login', [LoginController::class, 'login']);
```

### Authentication Flow

1. User visits your app behind Cloudflare Access
2. Cloudflare Access sends a JWT in the `Cf-Access-Jwt-Assertion` header
3. The package validates the JWT against Cloudflare's public keys
4. A user is created or updated with name, email, and groups from the JWT
5. The user is logged into Laravel's session

### Protecting Routes

Use Laravel's built-in `auth` middleware:

```php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

## Stateless / API Middleware Usage

The `LoginController` + session flow described above is the primary, recommended
way to authenticate a browser-based app and should be used for that purpose.
For API-style or machine-to-machine routes that can't hold a browser session
(for example, an MCP server route in a consuming app), this package also
ships an additional, opt-in `AuthenticateCloudflareAccess` middleware.

Unlike the login flow, this middleware authenticates the current request only
(via `Auth::setUser()`) and never writes anything to session storage. Apply
it explicitly to the routes that need it:

```php
use Jimbojsb\CloudflareAccess\Http\Middleware\AuthenticateCloudflareAccess;

Route::middleware(AuthenticateCloudflareAccess::class)->group(function () {
    Route::get('/api/mcp', [McpController::class, 'handle']);
});
```

The middleware:

- Reads the `Cf-Access-Jwt-Assertion` header and aborts with `401` if it's missing.
- Decodes and validates the JWT, aborting with `401` for malformed, invalid,
  expired, or wrong-audience tokens.
- Resolves (creating or updating, same as the login flow) the user via the
  configured `user_model`, and authenticates the request as that user without
  starting a session.
- Does not support the `user.json` local-development fallback that the login
  flow has — see "Local Development for the Middleware" below for its own,
  separate local-development story.

### Local Development for the Middleware

If you're calling this middleware-protected route from another app running
locally, there's no Cloudflare Access edge in front of either app to sign a
real assertion or re-mint a forwarded one. Set `trust_unverified_jwt` (env:
`CLOUDFLARE_ACCESS_TRUST_UNVERIFIED_JWT`) to opt into a mode where the
middleware trusts a JWT's claims without verifying its signature — it
defaults to `true` automatically when `APP_ENV=local` (not for `testing` or
`staging`, which should still exercise real verification), and is always
ignored (verification is always enforced) when `APP_ENV=production`,
regardless of this setting.

While in this mode, the middleware also accepts the token from a
`Cf-Access-Token` header when `Cf-Access-Jwt-Assertion` isn't present — this
is the header name a calling app would use to forward a token it received,
which normally only has meaning when a real Cloudflare Access edge re-mints
it into a `Cf-Access-Jwt-Assertion` before it reaches you. Locally, with no
edge to do that re-minting, the middleware accepts the forwarded header
directly instead.

## Service Auth Middleware (Service Tokens)

Cloudflare Access **service tokens** — used for service-to-service or
machine-to-machine traffic like a webhook receiver — produce a
differently-shaped JWT than user auth: instead of `email`/`custom.name`/
`custom.groups`, they carry a `common_name` claim identifying the service
token. There's no human user behind that traffic, so this package ships a
second, opt-in `AuthenticateCloudflareAccessService` middleware for it,
separate from `AuthenticateCloudflareAccess`:

```php
use Jimbojsb\CloudflareAccess\Http\Middleware\AuthenticateCloudflareAccessService;

Route::middleware(AuthenticateCloudflareAccessService::class)->group(function () {
    Route::post('/webhooks/incoming', [WebhookController::class, 'handle']);
});
```

It reads/validates the JWT the same way `AuthenticateCloudflareAccess` does
(same `Cf-Access-Jwt-Assertion` header, same `trust_unverified_jwt` /
`Cf-Access-Token` local-development story described above), but rejects a
normal user JWT (no `common_name`) and vice versa.

By default (`cloudflare-access.service_auth.resolve_user` is `false`), the
middleware doesn't touch Eloquent or `Auth` at all — it just validates the
token and attaches it to the request so your controller can read the
service's identity directly:

```php
$commonName = $request->attributes->get('cloudflare_access_service_jwt')->commonName;
```

### Integrating with Laravel Authorization

If your webhook (or other service-token) traffic needs to participate in
normal Laravel authorization — gates, policies, roles/permissions packages —
set `service_auth.resolve_user` to `true` (env:
`CLOUDFLARE_ACCESS_SERVICE_RESOLVE_USER`). The middleware will then
find-or-create a system user for the service token and call `Auth::setUser()`
with it (stateless — no session is written, same as the regular middleware),
so `$request->user()`, gates, and policies all work against it.

The model used is configurable via `service_auth.user_model` (env:
`CLOUDFLARE_ACCESS_SERVICE_USER_MODEL`): leave it unset to reuse the same
`user_model` as human auth (service identities share the `users` table), or
point it at a dedicated model to keep service identities in their own table.

A static `service_auth.groups` array (default `['service']`) is assigned to
every resolved service user, so you can gate on it:

```php
Gate::define('act-as-service', fn ($user) => in_array('service', $user->groups ?? []));
```

The user's `email` and `name` are derived from the JWT's `common_name` by
default as `{common_name}@{subdomain}.cloudflareaccess.com` and the
`common_name` itself, respectively. Override either with
`resolveEmailUsing()` / `resolveNameUsing()` — e.g. in your
`AppServiceProvider`'s `boot()` method:

```php
use Jimbojsb\CloudflareAccess\Http\Middleware\AuthenticateCloudflareAccessService;

AuthenticateCloudflareAccessService::resolveEmailUsing(
    fn (string $commonName) => "{$commonName}@my-app.internal"
);

AuthenticateCloudflareAccessService::resolveNameUsing(
    fn (string $commonName) => "Service: {$commonName}"
);
```

### Local Development

For local development without Cloudflare Access, create a `user.json` file in your project root:

```json
{
    "name": "Local Developer",
    "email": "dev@example.com",
    "groups": ["admin"]
}
```

This only works when `APP_ENV` is not `production`. Note that groups will only be populated if `CLOUDFLARE_ACCESS_POPULATE_GROUPS` is set to `true`.
For safety, you should add this file to your .gitinore. 

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE).
