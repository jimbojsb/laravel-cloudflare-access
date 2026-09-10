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
        $assertion = $request->header('Cf-Access-Jwt-Assertion');

        // Cloudflare Access always delivers a real assertion under
        // Cf-Access-Jwt-Assertion. There is no edge available in local
        // development to re-mint one, so a calling app forwarding a token
        // under Cf-Access-Token (the header used to *present* a token to
        // Access for re-minting) is accepted directly instead, but only
        // when we're already trusting unverified tokens.
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
