<?php

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Jimbojsb\CloudflareAccess\CloudflareAccessJWT;

it('can be instantiated with configuration', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience', 60);

    expect($jwt->getSubdomain())->toBe('testcompany');
    expect($jwt->getExpectedAudience())->toBe('test-audience');
});

it('validates required fields are present', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    expect($jwt->isValid())->toBeFalse();
});

it('validates timestamps correctly', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->email = 'test@example.com';
    $jwt->name = 'Test User';
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeTrue();
});

it('rejects expired tokens', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->email = 'test@example.com';
    $jwt->name = 'Test User';
    $jwt->issuedAt = Carbon::now()->subHours(2);
    $jwt->notBefore = Carbon::now()->subHours(2);
    $jwt->expiresAt = Carbon::now()->subMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('rejects tokens with invalid audience', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['wrong-audience'];
    $jwt->email = 'test@example.com';
    $jwt->name = 'Test User';
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('rejects tokens not yet valid', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->email = 'test@example.com';
    $jwt->name = 'Test User';
    $jwt->issuedAt = Carbon::now()->addMinutes(5);
    $jwt->notBefore = Carbon::now()->addMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('accepts tokens with matching audience in array', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['other-audience', 'test-audience', 'another-audience'];
    $jwt->email = 'test@example.com';
    $jwt->name = 'Test User';
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeTrue();
});

it('rejects tokens without name', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->email = 'test@example.com';
    $jwt->name = null;
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('rejects tokens without email', function () {
    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->email = null;
    $jwt->name = 'Test User';
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('trusts unverified tokens outside of production when configured to', function () {
    config(['app.env' => 'local']);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience', 60, true);

    expect($jwt->trustsUnverifiedTokens())->toBeTrue();
});

it('never trusts unverified tokens in production even when configured to', function () {
    config(['app.env' => 'production']);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience', 60, true);

    expect($jwt->trustsUnverifiedTokens())->toBeFalse();
});

it('does not trust unverified tokens when not configured to, even outside production', function () {
    config(['app.env' => 'local']);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience', 60, false);

    expect($jwt->trustsUnverifiedTokens())->toBeFalse();
});

it('decodes a token payload without verifying its signature when trusting unverified tokens', function () {
    config(['app.env' => 'local']);

    $payload = [
        'aud' => ['test-audience'],
        'email' => 'trusted@example.com',
        'iat' => time() - 60,
        'nbf' => time() - 60,
        'exp' => time() + 600,
        'custom' => ['name' => 'Trusted User', 'groups' => ['engineering']],
    ];

    // Signed with a throwaway key — decodeWithoutVerification never checks it.
    $token = JWT::encode($payload, 'an-irrelevant-secret-that-is-long-enough-for-hs256', 'HS256');

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience', 60, true);
    $jwt->decode($token);

    expect($jwt->email)->toBe('trusted@example.com');
    expect($jwt->name)->toBe('Trusted User');
    expect($jwt->groups)->toBe(['engineering']);
    expect($jwt->isValid())->toBeTrue();
});

it('rejects a malformed token even when trusting unverified tokens', function () {
    config(['app.env' => 'local']);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience', 60, true);

    $jwt->decode('not-a-real-jwt');
})->throws(UnexpectedValueException::class);
