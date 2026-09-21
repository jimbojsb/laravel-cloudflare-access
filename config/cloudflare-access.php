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

    /*
    |--------------------------------------------------------------------------
    | Trust Unverified JWTs
    |--------------------------------------------------------------------------
    |
    | When true, AuthenticateCloudflareAccess trusts a JWT's claims without
    | verifying its signature against Cloudflare's JWKS, and will also accept
    | the token from a Cf-Access-Token header (used by a calling app forwarding
    | a token) when Cf-Access-Jwt-Assertion isn't present. This is only useful
    | in local development, where there is no Cloudflare Access edge available
    | to sign a real assertion or re-mint a forwarded one. Defaults to true
    | only when APP_ENV is "local" — NOT for "testing"/"staging", which should
    | still exercise real verification. This setting is always ignored —
    | verification is always enforced — when APP_ENV is "production".
    |
    */
    'trust_unverified_jwt' => env('CLOUDFLARE_ACCESS_TRUST_UNVERIFIED_JWT', env('APP_ENV', 'production') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Service Auth (Service Token) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AuthenticateCloudflareAccessService, the opt-in
    | middleware for Cloudflare Access service tokens (e.g. for webhook
    | receivers or other machine-to-machine callers). Service token JWTs
    | carry a "common_name" claim instead of "email"/"name"/"groups".
    |
    */
    'service_auth' => [
        // When false (default), the middleware only validates the JWT and
        // exposes it on the request (via the `cloudflare_access_service_jwt`
        // request attribute). When true, it also resolves/creates a system
        // user and calls Auth::setUser(), so normal Laravel authorization
        // (gates, policies, roles/permissions packages) works against it.
        'resolve_user' => env('CLOUDFLARE_ACCESS_SERVICE_RESOLVE_USER', false),

        // The model used for resolved service users, only relevant when
        // resolve_user is true. Defaults to the same user_model as human
        // auth above (shared table); set this to a dedicated model to keep
        // service identities in their own table instead.
        'user_model' => env('CLOUDFLARE_ACCESS_SERVICE_USER_MODEL'),

        // Static groups/roles tag applied to resolved service users, for
        // apps that gate authorization on the groups column.
        'groups' => ['service'],
    ],
];
