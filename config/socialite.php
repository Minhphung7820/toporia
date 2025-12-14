<?php

declare(strict_types=1);

/**
 * Socialite Configuration
 *
 * Configuration for OAuth social authentication.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Google OAuth
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/socialite/google/callback'),
        'scopes' => [
            'openid',
            'profile',
            'email',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook OAuth
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID', ''),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/socialite/facebook/callback'),
        'scopes' => [
            'email',
            'public_profile',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub OAuth
    |--------------------------------------------------------------------------
    */
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/socialite/github/callback'),
        'scopes' => [
            'user:email',
        ],
    ],
];
