<?php

use Firebase\JWT\JWT;
use Jimbojsb\CloudflareAccess\Tests\Fixtures\User;

function makeCloudflareServiceAssertion(string $privateKey, string $kid, array $overrides = []): string
{
    $now = time();

    $payload = array_merge([
        'aud' => ['test-audience-id'],
        'common_name' => 'my-service.access',
        'iat' => $now - 60,
        'nbf' => $now - 60,
        'exp' => $now + 600,
        'iss' => 'https://testcompany.cloudflareaccess.com',
    ], $overrides);

    return JWT::encode($payload, $privateKey, 'RS256', $kid);
}

it('authenticates the request without resolving a user by default', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid);

    $response = $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service');

    $response->assertOk();
    $response->assertJson(['common_name' => 'my-service.access', 'user_email' => null]);

    expect(User::count())->toBe(0);
});

it('resolves and authenticates a system user when resolve_user is enabled', function () {
    config(['cloudflare-access.service_auth.resolve_user' => true]);

    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid);

    $response = $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service');

    $response->assertOk();
    $response->assertJson([
        'common_name' => 'my-service.access',
        'user_email' => 'my-service.access@testcompany.cloudflareaccess.com',
    ]);

    $user = User::where('email', 'my-service.access@testcompany.cloudflareaccess.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('my-service.access');
    expect($user->groups)->toBe(['service']);
});

it('returns 401 when the header is missing', function () {
    $this->get('/api/service')->assertStatus(401);
});

it('returns 401 for an invalid signature', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    [$otherPrivateKey] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($otherPrivateKey, $kid);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service')
        ->assertStatus(401);
});

it('returns 401 for an expired token', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid, [
        'iat' => time() - 1200,
        'nbf' => time() - 1200,
        'exp' => time() - 600,
    ]);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service')
        ->assertStatus(401);
});

it('returns 401 for a token with the wrong audience', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid, [
        'aud' => ['wrong-audience'],
    ]);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service')
        ->assertStatus(401);
});

it('returns 401 for a token without common_name', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid, [
        'common_name' => null,
    ]);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service')
        ->assertStatus(401);
});

it('returns 401 for a malformed token', function () {
    $this->withHeader('Cf-Access-Jwt-Assertion', 'not-a-real-jwt')
        ->get('/api/service')
        ->assertStatus(401);
});

it('trusts an unsigned service token via Cf-Access-Jwt-Assertion when trust_unverified_jwt is enabled', function () {
    config(['app.env' => 'local']);
    config(['cloudflare-access.trust_unverified_jwt' => true]);

    [$privateKey] = cloudflareAccessKeyPair();
    $assertion = makeCloudflareServiceAssertion($privateKey, 'unused-kid');

    $response = $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service');

    $response->assertOk();
    $response->assertJson(['common_name' => 'my-service.access']);
});

it('accepts a forwarded service token via Cf-Access-Token when the primary header is absent and trust_unverified_jwt is enabled', function () {
    config(['app.env' => 'local']);
    config(['cloudflare-access.trust_unverified_jwt' => true]);

    [$privateKey] = cloudflareAccessKeyPair();
    $assertion = makeCloudflareServiceAssertion($privateKey, 'unused-kid');

    $response = $this->withHeader('Cf-Access-Token', $assertion)
        ->get('/api/service');

    $response->assertOk();
    $response->assertJson(['common_name' => 'my-service.access']);
});

it('rejects a normal user JWT (no common_name) even though it is validly signed', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid, [
        'common_name' => null,
        'email' => 'jwtuser@example.com',
        'custom' => ['name' => 'JWT User', 'groups' => ['engineering']],
    ]);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/service')
        ->assertStatus(401);
});

it('applies a Gate check against the resolved service user groups', function () {
    config(['cloudflare-access.service_auth.resolve_user' => true]);

    \Illuminate\Support\Facades\Gate::define('act-as-service', function ($user) {
        return in_array('service', $user->groups ?? []);
    });

    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareServiceAssertion($privateKey, $kid);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)->get('/api/service')->assertOk();

    expect(\Illuminate\Support\Facades\Gate::allows('act-as-service'))->toBeTrue();
});
