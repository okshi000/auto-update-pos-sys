<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of locales supported by the application.
    |
    */
    'supported' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale for the application. Can be overridden via
    | APP_LOCALE environment variable.
    |
    */
    'default' => env('APP_LOCALE', 'ar'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The locale to use when the requested locale is not available.
    |
    */
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | RTL Languages
    |--------------------------------------------------------------------------
    |
    | List of right-to-left languages.
    |
    */
    'rtl' => ['ar', 'he', 'fa', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Locale Names
    |--------------------------------------------------------------------------
    |
    | Human-readable names for each locale.
    |
    */
    'names' => [
        'en' => [
            'native' => 'English',
            'localized' => 'English',
        ],
        'ar' => [
            'native' => 'العربية',
            'localized' => 'Arabic',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Formats
    |--------------------------------------------------------------------------
    |
    | Date format strings for each locale.
    |
    */
    'date_formats' => [
        'en' => [
            'short' => 'M j, Y',
            'long' => 'F j, Y',
            'datetime' => 'M j, Y g:i A',
        ],
        'ar' => [
            'short' => 'Y/m/d',
            'long' => 'j F Y',
            'datetime' => 'Y/m/d H:i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Number Formats
    |--------------------------------------------------------------------------
    |
    | Number formatting settings for each locale.
    |
    */
    'number_formats' => [
        'en' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'ar' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
    ],
];
