<?php

namespace Jimbojsb\CloudflareAccess\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jimbojsb\CloudflareAccess\CloudflareAccessJWT;
use Jimbojsb\CloudflareAccess\CloudflareAccessUserResolver;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCloudflareAccess
{
    public function __construct(
        protected CloudflareAccessJWT $jwt,
        protected CloudflareAccessUserResolver $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('Cf-Access-Jwt-Assertion')) {
            abort(401);
        }

        $assertion = $request->header('Cf-Access-Jwt-Assertion');

        try {
            $this->jwt->decode($assertion);
        } catch (\Throwable $exception) {
            abort(401);
        }

        if (! $this->jwt->isValid()) {
            abort(401);
        }

        $user = $this->users->resolve($this->jwt->email, $this->jwt->name, $this->jwt->groups);

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
