<?php

namespace Jimbojsb\CloudflareAccess\Http\Controllers;

use Jimbojsb\CloudflareAccess\CloudflareAccessJWT;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(
        protected CloudflareAccessJWT $jwt
    ) {}

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        if ($request->hasHeader('Cf-Access-Jwt-Assertion')) {
            return $this->authenticateWithCloudflare($request);
        }

        if ($this->canUseLocalUser()) {
            return $this->authenticateWithLocalUser();
        }

        abort(403);
    }

    protected function authenticateWithCloudflare(Request $request): RedirectResponse
    {
        $assertion = $request->header('Cf-Access-Jwt-Assertion');

        $this->jwt->decode($assertion);

        if (! $this->jwt->isValid()) {
            abort(403);
        }

        $user = $this->jwt->resolveUser();

        Auth::login($user);

        return redirect()->intended('/');
    }

    protected function authenticateWithLocalUser(): RedirectResponse
    {
        $userJsonPath = base_path('user.json');

        if (! file_exists($userJsonPath)) {
            abort(403, 'Could not find user.json');
        }

        $config = json_decode(file_get_contents($userJsonPath));

        $this->jwt->email = $config->email;
        $this->jwt->name = $config->name;
        $this->jwt->groups = $config->groups ?? [];

        $user = $this->jwt->resolveUser();

        Auth::login($user);

        return redirect()->intended('/');
    }

    protected function canUseLocalUser(): bool
    {
        return config('cloudflare-access.allow_local_user', true)
            && config('app.env') !== 'production';
    }
}
