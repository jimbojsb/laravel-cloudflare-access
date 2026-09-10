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
- Does not support the `user.json` local-development fallback — it is
  production-only behavior with no local shortcuts.

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
