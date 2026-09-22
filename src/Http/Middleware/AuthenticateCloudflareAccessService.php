<?php

namespace Jimbojsb\CloudflareAccess\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jimbojsb\CloudflareAccess\CloudflareAccessServiceJWT;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCloudflareAccessService
{
    protected static ?Closure $emailResolver = null;

    protected static ?Closure $nameResolver = null;

    public function __construct(
        protected CloudflareAccessServiceJWT $jwt
    ) {}

    /**
     * Derive the resolved service user's email from the JWT's common_name
     * using the given callback, instead of the default
     * "{common_name}@{subdomain}.cloudflareaccess.com" scheme.
     */
    public static function resolveEmailUsing(Closure $callback): void
    {
        static::$emailResolver = $callback;
    }

    /**
     * Derive the resolved service user's name from the JWT's common_name
     * using the given callback, instead of defaulting to the common_name
     * itself.
     */
    public static function resolveNameUsing(Closure $callback): void
    {
        static::$nameResolver = $callback;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $assertion = $request->header('Cf-Access-Jwt-Assertion');

        if (! $assertion && $this->jwt->trustsUnverifiedTokens()) {
            $assertion = $request->header('Cf-Access-Token');
        }

        if (! $assertion) {
            abort(401);
        }

        try {
            $this->jwt->decode($assertion);
        } catch (\Throwable $exception) {
            abort(401);
        }

        if (! $this->jwt->isValid()) {
            abort(401);
        }

        $request->attributes->set('cloudflare_access_service_jwt', $this->jwt);

        if (config('cloudflare-access.service_auth.resolve_user', false)) {
            $user = $this->resolveUser($this->jwt->commonName);

            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        }

        return $next($request);
    }

    protected function resolveUser(string $commonName): mixed
    {
        $userModel = config('cloudflare-access.service_auth.user_model') ?: config('cloudflare-access.user_model');

        $resolveEmail = static::$emailResolver ?? fn (string $commonName) => sprintf(
            '%s@%s.cloudflareaccess.com', $commonName, config('cloudflare-access.subdomain')
        );
        $resolveName = static::$nameResolver ?? fn (string $commonName) => $commonName;

        $user = $userModel::firstOrNew(['email' => $resolveEmail($commonName)]);
        $user->name = $resolveName($commonName);
        $user->groups = config('cloudflare-access.service_auth.groups', ['service']);
        $user->save();

        return $user;
    }
}
