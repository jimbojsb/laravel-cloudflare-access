<?php

use Jimbojsb\CloudflareAccess\CloudflareAccessUserResolver;
use Jimbojsb\CloudflareAccess\Tests\Fixtures\User;

it('creates a new user with groups when populate_groups is true', function () {
    $resolver = new CloudflareAccessUserResolver(User::class, true);

    $user = $resolver->resolve('new-user@example.com', 'New User', ['engineering', 'admin']);

    expect($user->email)->toBe('new-user@example.com');
    expect($user->name)->toBe('New User');
    expect($user->groups)->toBe(['engineering', 'admin']);
    expect(User::where('email', 'new-user@example.com')->count())->toBe(1);
});

it('does not populate groups for a new user when populate_groups is false', function () {
    $resolver = new CloudflareAccessUserResolver(User::class, false);

    $user = $resolver->resolve('no-groups@example.com', 'No Groups', ['engineering']);

    expect($user->groups)->toBe([]);
});

it('updates an existing user and preserves groups when populate_groups is false', function () {
    $existing = User::create([
        'name' => 'Old Name',
        'email' => 'existing@example.com',
        'groups' => ['viewer'],
    ]);

    $resolver = new CloudflareAccessUserResolver(User::class, false);

    $user = $resolver->resolve('existing@example.com', 'New Name', ['admin']);

    expect($user->id)->toBe($existing->id);
    expect($user->name)->toBe('New Name');
    expect($user->groups)->toBe(['viewer']);
});

it('updates an existing user and overwrites groups when populate_groups is true', function () {
    $existing = User::create([
        'name' => 'Old Name',
        'email' => 'existing@example.com',
        'groups' => ['viewer'],
    ]);

    $resolver = new CloudflareAccessUserResolver(User::class, true);

    $user = $resolver->resolve('existing@example.com', 'New Name', ['admin']);

    expect($user->id)->toBe($existing->id);
    expect($user->groups)->toBe(['admin']);
});
