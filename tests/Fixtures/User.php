<?php

namespace Jimbojsb\CloudflareAccess\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'roles'];

    protected $casts = [
        'roles' => 'array',
    ];
}
