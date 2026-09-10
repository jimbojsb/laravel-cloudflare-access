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
