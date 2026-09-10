<?php

use Carbon\Carbon;
use Jimbojsb\CloudflareAccess\CloudflareAccessJWT;
use Jimbojsb\CloudflareAccess\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('creates a new user with groups when populate_groups is true', function () {
    config(['cloudflare-access.populate_groups' => true]);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');
    $jwt->email = 'new-user@example.com';
    $jwt->name = 'New User';
    $jwt->groups = ['engineering', 'admin'];

    $user = $jwt->resolveUser();

    expect($user->email)->toBe('new-user@example.com');
    expect($user->name)->toBe('New User');
    expect($user->groups)->toBe(['engineering', 'admin']);
    expect(User::where('email', 'new-user@example.com')->count())->toBe(1);
});

it('does not populate groups for a new user when populate_groups is false', function () {
    config(['cloudflare-access.populate_groups' => false]);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');
    $jwt->email = 'no-groups@example.com';
    $jwt->name = 'No Groups';
    $jwt->groups = ['engineering'];

    $user = $jwt->resolveUser();

    expect($user->groups)->toBe([]);
});

it('updates an existing user and preserves groups when populate_groups is false', function () {
    config(['cloudflare-access.populate_groups' => false]);

    $existing = User::create([
        'name' => 'Old Name',
        'email' => 'existing@example.com',
        'groups' => ['viewer'],
    ]);

    $jwt = new CloudflareAccessJWT('testcompany', 'test-audience');
    $jwt->email = 'existing@example.com';
    $jwt->name = 'New Name';
    $jwt->groups = ['admin'];

    $user = $jwt->resolveUser();

    expect($user->id)->toBe($existing->id);
    expect($user->name)->toBe('New Name');
    expect($user->groups)->toBe(['viewer']);
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
