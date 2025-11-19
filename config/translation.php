<?php

declare(strict_types=1);

/**
 * Translation Configuration
 *
 * Configuration for the translation system.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Path
    |--------------------------------------------------------------------------
    |
    | Base path to translation files.
    | Default: resources/lang
    |
    | File structure:
    | resources/lang/
    |   en/
    |     messages.php
    |     validation.php
    |   vi/
    |     messages.php
    |     validation.php
    |
    */
    'path' => env('TRANSLATION_PATH', base_path('resources/lang')),

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale to use when a translation is not found in the
    | current locale. This should match one of the locales in the
    | 'available_locales' array.
    |
    */
    'fallback' => env('TRANSLATION_FALLBACK', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | List of available locales for the application.
    | Used for validation and locale switching.
    |
    */
    'available_locales' => [
        'en' => 'English',
        'vi' => 'Tiếng Việt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Translations
    |--------------------------------------------------------------------------
    |
    | Whether to cache loaded translations.
    | Improves performance but requires cache clearing when translations change.
    |
    */
    'cache' => env('TRANSLATION_CACHE', true),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Time to live for translation cache in seconds.
    | Default: 3600 (1 hour)
    |
    */
    'cache_ttl' => env('TRANSLATION_CACHE_TTL', 3600),
];

