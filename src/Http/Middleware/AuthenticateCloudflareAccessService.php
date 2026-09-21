<?php

namespace Jimbojsb\CloudflareAccess\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jimbojsb\CloudflareAccess\CloudflareAccessServiceJWT;
use Jimbojsb\CloudflareAccess\Contracts\ServiceUserIdentityResolver;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCloudflareAccessService
{
    public function __construct(
        protected CloudflareAccessServiceJWT $jwt,
        protected ServiceUserIdentityResolver $identityResolver
    ) {}

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

        $user = $userModel::firstOrNew(['email' => $this->identityResolver->email($commonName)]);
        $user->name = $this->identityResolver->name($commonName);
        $user->groups = config('cloudflare-access.service_auth.groups', ['service']);
        $user->save();

        return $user;
    }
}
