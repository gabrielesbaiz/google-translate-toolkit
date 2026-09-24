<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Here you may specify the API key generated within the Google Cloud
    | console for a project that has the "Cloud Translation API" enabled.
    | The base URL is only ever changed to point at a proxy or a mock.
    |
    */

    'api_key' => env('GOOGLE_TRANSLATE_API_KEY', env('GOOGLE_DEVELOPER_KEY')),

    'base_url' => env('GOOGLE_TRANSLATE_BASE_URL', 'https://translation.googleapis.com/language/translate/v2'),

    /*
    |--------------------------------------------------------------------------
    | Default Languages
    |--------------------------------------------------------------------------
    |
    | These options control the languages used when a call does not name them.
    | A null source lets Google detect the language, which is cheaper and more
    | accurate than guessing, while a null target falls back to your locale.
    |
    */

    'default_source' => env('GOOGLE_TRANSLATE_SOURCE'),

    'default_target' => env('GOOGLE_TRANSLATE_TARGET'),

    'default_format' => env('GOOGLE_TRANSLATE_FORMAT', 'text'),

    /*
    |--------------------------------------------------------------------------
    | Strict Language Codes
    |--------------------------------------------------------------------------
    |
    | This option determines whether language codes outside the known ISO
    | 639-1 set are rejected. You may disable it to pass through any code
    | that Google happens to support before this package knows about it.
    |
    */

    'strict_languages' => true,

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | These options are handed to Laravel's HTTP client on every outgoing
    | request. Failed requests are retried with an exponential backoff,
    | and chunks of a large batch are sent concurrently through a pool.
    |
    */

    'http' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'retry' => [
            'times' => 3,
            'sleep' => 250,
            'backoff' => true,
        ],
        'concurrency' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | The v2 endpoint accepts at most 128 segments and roughly 200k bytes per
    | request. These options keep each request comfortably inside those
    | limits, and any batch larger than this is split transparently.
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
    | reach the API, which is the single biggest cost saver in this package,
    | so you may want to give them a generous time to live.
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
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | These options guard your Google quota with Laravel's rate limiter before
    | a request ever leaves the application. Once the limit is reached, a
    | RateLimitExceededException is thrown instead of a request being sent.
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
    | When this option is enabled, a failing API call returns the original
    | text instead of throwing. This is useful in queued jobs and webhooks
    | where a missing translation must never fail the whole pipeline.
    |
    */

    'fallback_to_source' => env('GOOGLE_TRANSLATE_FALLBACK_TO_SOURCE', false),

    'events' => true,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | These options determine the connection and queue used by the jobs this
    | package dispatches. When they are left null, the jobs are dispatched
    | onto the default connection and queue of your application.
    |
    */

    'queue' => [
        'connection' => env('GOOGLE_TRANSLATE_QUEUE_CONNECTION'),
        'queue' => env('GOOGLE_TRANSLATE_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent Attributes
    |--------------------------------------------------------------------------
    |
    | This option controls the suffix used by the HasTranslations concern to
    | name the column a translation is written to. With the value below, a
    | "body" attribute translated into Italian is stored in "body_it".
    |
    */

    'attribute_suffix' => '_{locale}',

    /*
    |--------------------------------------------------------------------------
    | Placeholder Protection
    |--------------------------------------------------------------------------
    |
    | Tokens matching these patterns are masked before the text reaches Google
    | and restored once it comes back, so ":name", "{count}" and URLs survive
    | the round trip intact. Your own patterns are merged with the built-in ones.
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
    | The protected terms listed here are never translated, which is where
    | brand names belong. The overrides force your own domain wording per
    | target locale and are applied after the translation comes back.
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
    | Pricing & Budget
    |--------------------------------------------------------------------------
    |
    | Google bills per million characters, so these options let the package
    | price a call before you make it. Once the daily character budget is
    | reached, a BudgetExceededException is thrown instead of a request.
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
    | Usage Statistics
    |--------------------------------------------------------------------------
    |
    | These options control the daily counters behind the "translate:stats"
    | command, which report your translation volume, cache hit rate and
    | estimated spend. The counters are kept in your cache store.
    |
    */

    'stats' => [
        'enabled' => true,
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Middleware
    |--------------------------------------------------------------------------
    |
    | The "translate.response" middleware translates the JSON response paths
    | listed here into the language negotiated from the Accept-Language
    | header. Dot notation and wildcards are supported, and this can get
    | expensive, so the middleware stays disabled until you enable it.
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
