<?php

namespace Jimbojsb\CloudflareAccess\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jimbojsb\CloudflareAccess\CloudflareAccessJWT;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCloudflareAccess
{
    public function __construct(
        protected CloudflareAccessJWT $jwt
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

        $groups = config('cloudflare-access.populate_groups', false) ? $this->jwt->groups : null;
        $user = $this->findOrCreateUser($this->jwt->email, $this->jwt->name, $groups);

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    protected function findOrCreateUser(string $email, string $name, ?array $groups = null): mixed
    {
        $userModel = config('cloudflare-access.user_model');

        $user = $userModel::firstOrNew(['email' => strtolower($email)]);
        $user->name = $name;

        if ($groups !== null) {
            $user->groups = $groups;
        } elseif ($user->groups === null) {
            $user->groups = [];
        }

        $user->save();

        return $user;
    }
}
