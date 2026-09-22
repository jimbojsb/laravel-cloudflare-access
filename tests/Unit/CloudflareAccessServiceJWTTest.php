<?php

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Jimbojsb\CloudflareAccess\CloudflareAccessServiceJWT;

it('validates required fields are present', function () {
    $jwt = new CloudflareAccessServiceJWT('testcompany', 'test-audience');

    expect($jwt->isValid())->toBeFalse();
});

it('validates timestamps correctly', function () {
    $jwt = new CloudflareAccessServiceJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->commonName = 'my-service.access';
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeTrue();
});

it('rejects tokens without common_name', function () {
    $jwt = new CloudflareAccessServiceJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->commonName = null;
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('rejects tokens with invalid audience', function () {
    $jwt = new CloudflareAccessServiceJWT('testcompany', 'test-audience');

    $jwt->audience = ['wrong-audience'];
    $jwt->commonName = 'my-service.access';
    $jwt->issuedAt = Carbon::now()->subMinutes(5);
    $jwt->notBefore = Carbon::now()->subMinutes(5);
    $jwt->expiresAt = Carbon::now()->addMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('rejects expired tokens', function () {
    $jwt = new CloudflareAccessServiceJWT('testcompany', 'test-audience');

    $jwt->audience = ['test-audience'];
    $jwt->commonName = 'my-service.access';
    $jwt->issuedAt = Carbon::now()->subHours(2);
    $jwt->notBefore = Carbon::now()->subHours(2);
    $jwt->expiresAt = Carbon::now()->subMinutes(30);

    expect($jwt->isValid())->toBeFalse();
});

it('decodes a service token payload, populating common_name and not email/name/groups', function () {
    config(['app.env' => 'local']);

    $payload = [
        'aud' => ['test-audience'],
        'common_name' => 'my-service.access',
        'iat' => time() - 60,
        'nbf' => time() - 60,
        'exp' => time() + 600,
    ];

    $token = JWT::encode($payload, 'an-irrelevant-secret-that-is-long-enough-for-hs256', 'HS256');

    $jwt = new CloudflareAccessServiceJWT('testcompany', 'test-audience', 60, true);
    $jwt->decode($token);

    expect($jwt->commonName)->toBe('my-service.access');
    expect($jwt->email)->toBeNull();
    expect($jwt->name)->toBeNull();
    expect($jwt->groups)->toBe([]);
    expect($jwt->isValid())->toBeTrue();
});
