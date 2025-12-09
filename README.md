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

The migration creates a `users` table with `id`, `name`, `email`, `roles` (json), and timestamps.

## Configuration

Add to your `.env`:

```env
CLOUDFLARE_ACCESS_SUBDOMAIN=yourcompany
CLOUDFLARE_ACCESS_AUDIENCE=your-application-audience-tag
```

- `CLOUDFLARE_ACCESS_SUBDOMAIN`: Your team domain subdomain (e.g., if your domain is `yourcompany.cloudflareaccess.com`, use `yourcompany`)
- `CLOUDFLARE_ACCESS_AUDIENCE`: The Application Audience (AUD) Tag from Cloudflare Zero Trust dashboard

### User Model

Your User model needs `name`, `email`, and `roles` columns. Update `config/cloudflare-access.php` if using a different model:

```php
'user_model' => App\Models\User::class,
```

Ensure your model casts roles as an array:

```php
protected $casts = [
    'roles' => 'array',
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

### Local Development

For local development without Cloudflare Access, create a `user.json` file in your project root:

```json
{
    "name": "Local Developer",
    "email": "dev@example.com",
    "roles": ["admin"]
}
```

This only works when `APP_ENV` is not `production`.

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE).
