<?php

use Carbon\Carbon;
use Jimbojsb\CloudflareAccess\CloudflareAccessJWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

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
