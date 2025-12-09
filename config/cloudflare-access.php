<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare Access Audience Tag
    |--------------------------------------------------------------------------
    |
    | The Application Audience (AUD) Tag for your Cloudflare Access application.
    | Find this in the Cloudflare Zero Trust dashboard under
    | Access > Applications > [Your App] > Overview.
    |
    */
    'audience' => env('CLOUDFLARE_ACCESS_AUDIENCE'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Access Team Domain Subdomain
    |--------------------------------------------------------------------------
    |
    | Your Cloudflare Access team domain subdomain. If your team domain is
    | "mycompany.cloudflareaccess.com", the subdomain is "mycompany".
    |
    */
    'subdomain' => env('CLOUDFLARE_ACCESS_SUBDOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used for users. This model should have 'name', 'email',
    | and 'groups' columns.
    |
    */
    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Populate Groups
    |--------------------------------------------------------------------------
    |
    | Whether to populate the groups column from the Cloudflare Access JWT.
    | When false, only name and email are synced to the user model.
    |
    */
    'populate_groups' => env('CLOUDFLARE_ACCESS_POPULATE_GROUPS', false),

    /*
    |--------------------------------------------------------------------------
    | JWK Cache Duration
    |--------------------------------------------------------------------------
    |
    | The number of minutes to cache the Cloudflare Access JWK keys.
    |
    */
    'jwk_cache_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Local Development Configuration
    |--------------------------------------------------------------------------
    |
    | When not in production, you can use a local user.json file to simulate
    | authentication. Set this to false to disable this feature.
    |
    */
    'allow_local_user' => env('CLOUDFLARE_ACCESS_ALLOW_LOCAL_USER', true),
];
