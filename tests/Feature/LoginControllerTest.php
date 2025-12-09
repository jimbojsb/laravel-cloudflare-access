<?php

use Jimbojsb\CloudflareAccess\Tests\Fixtures\User;
use Illuminate\Support\Facades\Auth;

it('redirects authenticated users', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'groups' => [],
    ]);

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/');
});

it('returns 403 without cloudflare header in production', function () {
    config(['app.env' => 'production']);

    $this->get('/login')
        ->assertStatus(403);
});

it('can authenticate with local user.json in non-production', function () {
    config(['app.env' => 'local']);

    $userJson = json_encode([
        'name' => 'Local User',
        'email' => 'local@example.com',
        'groups' => ['admin'],
    ]);

    file_put_contents(base_path('user.json'), $userJson);

    $this->get('/login')
        ->assertRedirect('/');

    expect(Auth::check())->toBeTrue();
    expect(Auth::user()->email)->toBe('local@example.com');
    expect(Auth::user()->name)->toBe('Local User');

    @unlink(base_path('user.json'));
});

it('returns 403 when user.json is missing in non-production', function () {
    config(['app.env' => 'local']);

    @unlink(base_path('user.json'));

    $this->get('/login')
        ->assertStatus(403);
});

it('creates or updates user from local user.json with groups when populate_groups is true', function () {
    config(['app.env' => 'local']);
    config(['cloudflare-access.populate_groups' => true]);

    $userJson = json_encode([
        'name' => 'Initial Name',
        'email' => 'user@example.com',
        'groups' => ['viewer'],
    ]);

    file_put_contents(base_path('user.json'), $userJson);

    $this->get('/login');

    $user = User::where('email', 'user@example.com')->first();
    expect($user->name)->toBe('Initial Name');
    expect($user->groups)->toBe(['viewer']);

    Auth::logout();

    $userJson = json_encode([
        'name' => 'Updated Name',
        'email' => 'user@example.com',
        'groups' => ['admin', 'editor'],
    ]);

    file_put_contents(base_path('user.json'), $userJson);

    $this->get('/login');

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->groups)->toBe(['admin', 'editor']);

    expect(User::where('email', 'user@example.com')->count())->toBe(1);

    @unlink(base_path('user.json'));
});

it('does not populate groups when populate_groups is false', function () {
    config(['app.env' => 'local']);
    config(['cloudflare-access.populate_groups' => false]);

    $userJson = json_encode([
        'name' => 'Test User',
        'email' => 'nogroups@example.com',
        'groups' => ['admin', 'editor'],
    ]);

    file_put_contents(base_path('user.json'), $userJson);

    $this->get('/login');

    $user = User::where('email', 'nogroups@example.com')->first();
    expect($user->name)->toBe('Test User');
    expect($user->groups)->toBe([]);

    @unlink(base_path('user.json'));
});
