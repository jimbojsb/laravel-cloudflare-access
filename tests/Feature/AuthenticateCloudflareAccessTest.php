<?php

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Jimbojsb\CloudflareAccess\Tests\Fixtures\User;

function cloudflareAccessKeyPair(): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $privateKey);
    $details = openssl_pkey_get_details($resource);

    return [$privateKey, $details['rsa']];
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function fakeCloudflareJwks(array $rsaDetails, string $kid): void
{
    Http::fake([
        'https://testcompany.cloudflareaccess.com/cdn-cgi/access/certs' => Http::response([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $kid,
                    'n' => base64UrlEncode($rsaDetails['n']),
                    'e' => base64UrlEncode($rsaDetails['e']),
                ],
            ],
        ]),
    ]);
}

function makeCloudflareAssertion(string $privateKey, string $kid, array $overrides = []): string
{
    $now = time();

    $payload = array_merge([
        'aud' => ['test-audience-id'],
        'email' => 'jwtuser@example.com',
        'iat' => $now - 60,
        'nbf' => $now - 60,
        'exp' => $now + 600,
        'iss' => 'https://testcompany.cloudflareaccess.com',
        'custom' => [
            'name' => 'JWT User',
            'groups' => ['engineering'],
        ],
    ], $overrides);

    return JWT::encode($payload, $privateKey, 'RS256', $kid);
}

it('authenticates the request without a session when the token is valid', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareAssertion($privateKey, $kid);

    $response = $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/me');

    $response->assertOk();
    $response->assertJson(['email' => 'jwtuser@example.com']);

    $user = User::where('email', 'jwtuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('JWT User');
});

it('returns 401 when the header is missing', function () {
    $this->get('/api/me')->assertStatus(401);
});

it('returns 401 for an invalid signature', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    [$otherPrivateKey] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareAssertion($otherPrivateKey, $kid);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/me')
        ->assertStatus(401);
});

it('returns 401 for an expired token', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareAssertion($privateKey, $kid, [
        'iat' => time() - 1200,
        'nbf' => time() - 1200,
        'exp' => time() - 600,
    ]);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/me')
        ->assertStatus(401);
});

it('returns 401 for a token with the wrong audience', function () {
    [$privateKey, $rsaDetails] = cloudflareAccessKeyPair();
    $kid = 'test-key-id';
    fakeCloudflareJwks($rsaDetails, $kid);

    $assertion = makeCloudflareAssertion($privateKey, $kid, [
        'aud' => ['wrong-audience'],
    ]);

    $this->withHeader('Cf-Access-Jwt-Assertion', $assertion)
        ->get('/api/me')
        ->assertStatus(401);
});

it('returns 401 for a malformed token', function () {
    $this->withHeader('Cf-Access-Jwt-Assertion', 'not-a-real-jwt')
        ->get('/api/me')
        ->assertStatus(401);
});
