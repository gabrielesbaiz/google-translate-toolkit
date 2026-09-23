<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | API key generated within the Google Cloud console with the
    | "Cloud Translation API" enabled.
    |
    */

    'api_key' => env('GOOGLE_TRANSLATE_API_KEY', env('GOOGLE_DEVELOPER_KEY')),

    'base_url' => env('GOOGLE_TRANSLATE_BASE_URL', 'https://translation.googleapis.com/language/translate/v2'),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | "default_source" may be null: Google will then auto-detect the source
    | language, which is both cheaper and more accurate than guessing.
    | "default_target" falls back to the application locale when null.
    |
    */

    'default_source' => env('GOOGLE_TRANSLATE_SOURCE'),

    'default_target' => env('GOOGLE_TRANSLATE_TARGET'),

    'default_format' => env('GOOGLE_TRANSLATE_FORMAT', 'text'),

    /*
    | Reject language codes that are not part of the known ISO 639-1 set.
    | Disable it to pass through any code Google may support in the future.
    */

    'strict_languages' => true,

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retry' => [
            'times' => 3,
            'sleep' => 250,
            'backoff' => true,
        ],
        // Send several chunks concurrently through Http::pool().
        'concurrency' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | The v2 endpoint accepts at most 128 segments and roughly 200k bytes per
    | request. Batches larger than this are split transparently.
    |
    */

    'chunk' => [
        'max_segments' => 100,
        'max_characters' => 20000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Translations are deterministic enough to cache. Cached segments never
    | reach the API, which is the single biggest cost saver of this package.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => env('GOOGLE_TRANSLATE_CACHE_STORE'),
        'ttl' => 60 * 60 * 24 * 30,
        'prefix' => 'gtt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Guards the quota with Laravel's RateLimiter before hitting the API.
    |
    */

    'rate_limit' => [
        'enabled' => false,
        'key' => 'google-translate-toolkit',
        'max_per_minute' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resilience
    |--------------------------------------------------------------------------
    |
    | When "fallback_to_source" is true a failing API call returns the original
    | text instead of throwing. Useful in queued jobs and webhooks where a
    | missing translation must never fail the whole pipeline.
    |
    */

    'fallback_to_source' => env('GOOGLE_TRANSLATE_FALLBACK_TO_SOURCE', false),

    'events' => true,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('GOOGLE_TRANSLATE_QUEUE_CONNECTION'),
        'queue' => env('GOOGLE_TRANSLATE_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent
    |--------------------------------------------------------------------------
    |
    | Suffix used by the HasTranslations concern: "body" translated to "it"
    | is written to "body_it".
    |
    */

    'attribute_suffix' => '_{locale}',

    /*
    |--------------------------------------------------------------------------
    | Placeholder protection
    |--------------------------------------------------------------------------
    |
    | Tokens matching these patterns are masked before the text reaches Google
    | and restored afterwards, so ":name", "{count}" or URLs survive intact.
    | Your own patterns are merged with the built-in ones.
    |
    */

    'placeholders' => [
        'enabled' => true,
        'patterns' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Glossary
    |--------------------------------------------------------------------------
    |
    | "protect" lists terms that must never be translated (brand names).
    | "overrides" forces domain wording per target locale and is applied after
    | the translation comes back.
    |
    */

    'glossary' => [
        'protect' => [],
        'overrides' => [
            // 'it' => ['bounce' => 'rifiuto'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing & budget
    |--------------------------------------------------------------------------
    |
    | Google bills per million characters. "max_characters_per_day" throws a
    | BudgetExceededException before the request leaves your application.
    |
    */

    'pricing' => [
        'per_million' => 20.00,
        'currency' => 'USD',
    ],

    'budget' => [
        'max_characters_per_day' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage statistics
    |--------------------------------------------------------------------------
    */

    'stats' => [
        'enabled' => true,
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response middleware
    |--------------------------------------------------------------------------
    |
    | Opt-in: translates the listed JSON response paths to the language
    | negotiated from the Accept-Language header. Dot notation and wildcards
    | are supported. This can get expensive - enable it deliberately.
    |
    */

    'middleware' => [
        'enabled' => false,
        'fields' => [
            // 'data.*.title',
        ],
        'locales' => [],
    ],

];
