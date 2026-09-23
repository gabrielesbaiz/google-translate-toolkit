<p align="center">
    <img src="art/google-translate-toolkit-logo.png" alt="GoogleTranslateToolkit" width="600">
</p>

# GoogleTranslateToolkit

Google Translate for Laravel, built the way the rest of your application is built — a fluent, immutable builder, a cache that means most translations never leave your server, placeholders that survive the round trip, a cost estimate before you spend, and a fake for your tests.

[![Latest version](https://img.shields.io/packagist/v/gabrielesbaiz/google-translate-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/google-translate-toolkit)
[![PHP](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/google-translate-toolkit/php?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/google-translate-toolkit/illuminate%2Fsupport?style=flat-square&label=laravel)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/google-translate-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/google-translate-toolkit)
[![Stars](https://img.shields.io/github/stars/gabrielesbaiz/google-translate-toolkit?style=flat-square&logo=github)](https://github.com/gabrielesbaiz/google-translate-toolkit/stargazers)
[![Sponsor](https://img.shields.io/github/sponsors/gabrielesbaiz?style=flat-square&label=sponsor&logo=github)](https://github.com/sponsors/gabrielesbaiz)

> [!CAUTION]
> **Upgrading from 1.x?** Read [UPGRADING.md](UPGRADING.md) first. Your existing
> calls keep working and the published 1.x config is still read, but the Blade
> directive's arguments were **swapped in 1.x** and are now correct, and
> `unlessLanguageIs()` has a single return type. Two changes may need an edit.

> [!IMPORTANT]
> A ⭐ costs you nothing and helps other developers find this package.
> [Sponsoring](https://github.com/sponsors/gabrielesbaiz) keeps it compatible
> with every new Laravel release.

---

## Contents

- [What it does](#what-it-does)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Configuration](#configuration)
- [Core concepts](#core-concepts)
- [Translating](#translating)
- [Detecting languages](#detecting-languages)
- [Many languages at once](#many-languages-at-once)
- [Nested payloads](#nested-payloads)
- [Very large sets](#very-large-sets)
- [Placeholders](#placeholders)
- [Glossary](#glossary)
- [Caching](#caching)
- [Resilience](#resilience)
- [Cost, budget and statistics](#cost-budget-and-statistics)
- [Quality assurance](#quality-assurance)
- [Eloquent models](#eloquent-models)
- [Queues and deferred work](#queues-and-deferred-work)
- [Blade, macros and helpers](#blade-macros-and-helpers)
- [Validation and casting](#validation-and-casting)
- [Response middleware](#response-middleware)
- [Artisan commands](#artisan-commands)
- [Testing](#testing)
- [Events](#events)
- [Extending](#extending)
- [API reference](#api-reference)
- [Recipes](#recipes)
- [Troubleshooting](#troubleshooting)
- [Upgrading from 1.x](#upgrading-from-1x)
- [Contributing](#contributing)
- [Credits](#credits)
- [Support this package](#support-this-package)
- [Disclaimer](#disclaimer)
- [License](#license)

---

## What it does

Calling Google Translate is four HTTP requests' worth of API surface. Everything
hard about it happens around that call: placeholders that come back mangled,
the same string paid for twice, a webhook that fails because Google had a bad
minute, a bill nobody estimated, and a test suite that hits the network.

GoogleTranslateToolkit is the layer around the call.

- **Laravel's own HTTP client.** No Google SDK, no gRPC, no protobuf shims. Four REST endpoints, `Http::pool()`, `Http::fake()`.
- **A cache that actually saves money.** Cached segments never reach the API, and a batch of a hundred strings only sends the ones it has not seen.
- **Placeholders survive.** `:name`, `{count}`, `{{ $blade }}`, `%s`, URLs, e-mail addresses, `@handles` and code spans are masked before the call and restored after.
- **A glossary.** Brand names that must never be translated, and domain wording forced per locale.
- **137 languages** as a backed enum, normalised (`ZH_tw` → `zh-TW`), validated, with labels and RTL flags.
- **Cost control.** `estimate()` before you spend, a daily character budget that throws rather than bills, and a stats table with the cache hit rate.
- **Eloquent integration.** Mirror `body` into `body_it`, find the rows still missing, backfill a table in one command.
- **Quality assurance.** Back-translate a string and score what survived; audit a whole lang file the same way.
- **A fake.** `GoogleTranslate::fake()` with `assertTranslated()` — deterministic, offline, readable.

Version 2.0 is a full rewrite: 52 source files, 93 tests, PHPStan level 6, zero
network access in the suite.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- A Google Cloud API key with the **Cloud Translation API** enabled

No database tables, no migrations, no published assets.

## Installation

```bash
composer require gabrielesbaiz/google-translate-toolkit
```

Publish the configuration:

```bash
php artisan vendor:publish --tag="google-translate-toolkit-config"
```

Add the key to your `.env`:

```dotenv
GOOGLE_TRANSLATE_API_KEY=your-cloud-console-key
GOOGLE_TRANSLATE_TARGET=it
```

Check it works:

```bash
php artisan translate:text "Hello world" --to=it
```

The service provider is auto-discovered. Two facade aliases are registered —
`GoogleTranslateToolkit` (the 1.x name) and `GoogleTranslate` (shorter) — and
both resolve the same singleton.

### Getting an API key

1. Open the [Google Cloud console](https://console.cloud.google.com/) and select or create a project.
2. Enable **Cloud Translation API** under *APIs & Services → Library*.
3. Create an **API key** under *APIs & Services → Credentials*.
4. Restrict it: *API restrictions → Cloud Translation API*, and an IP restriction if your servers have fixed addresses.

The key travels in an `X-goog-api-key` header, never in the query string, so it
does not end up in proxy logs, browser history or error trackers.

## Quick start

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslate;

// The short way
GoogleTranslate::justTranslate('The message bounced');
// "Il messaggio è rimbalzato"

// The full object
$translation = GoogleTranslate::translate('The message bounced');
$translation->translatedText;    // "Il messaggio è rimbalzato"
$translation->sourceLanguage;    // "en" — detected by Google
$translation->cached;            // false the first time, true after

// The builder
GoogleTranslate::from('en')->to('it')->asHtml()->text('<b>Hello</b>');

// Many languages, one pooled fan-out
GoogleTranslate::to(['it', 'fr', 'de'])->translate('Good morning');

// A batch — one request, in order
GoogleTranslate::translateBatch(['one', 'two', 'three']);
```

## Configuration

The published file is `config/google-translate-toolkit.php`. Every key has a
working default; you can delete the ones you do not care about.

### Credentials

```php
'api_key' => env('GOOGLE_TRANSLATE_API_KEY', env('GOOGLE_DEVELOPER_KEY')),
'base_url' => env('GOOGLE_TRANSLATE_BASE_URL', 'https://translation.googleapis.com/language/translate/v2'),
```

`GOOGLE_DEVELOPER_KEY` is read as a fallback so 1.x applications keep working
untouched. `base_url` exists for proxies and for pointing the package at a mock
server in staging.

### Defaults

```php
'default_source' => env('GOOGLE_TRANSLATE_SOURCE'),   // null = auto-detect
'default_target' => env('GOOGLE_TRANSLATE_TARGET'),   // null = app.locale
'default_format' => env('GOOGLE_TRANSLATE_FORMAT', 'text'),
'strict_languages' => true,
```

Leaving `default_source` empty is the recommended setting: Google detects the
source language itself, which costs the same, is more accurate than guessing,
and means a string that is already Italian is not translated *from* English.

`strict_languages` validates every code against the bundled enum. Set it to
`false` to pass unknown codes straight through to Google — useful the week a new
language ships and before the package catches up.

### HTTP

```php
'http' => [
    'timeout' => 10,
    'connect_timeout' => 5,
    'retry' => [
        'times' => 3,
        'sleep' => 250,     // milliseconds
        'backoff' => true,  // 250, 500, 1000…
    ],
    'concurrency' => 5,     // parallel requests per pool
],
```

Retries fire on connection errors, `429` and `5xx`. A `4xx` other than `429` is
your mistake, not Google's, and is not retried.

### Chunking

```php
'chunk' => [
    'max_segments' => 100,     // the v2 endpoint accepts up to 128
    'max_characters' => 20000,
],
```

Batches larger than either limit are split transparently, sent through a bounded
`Http::pool()`, and reassembled in the original order.

### Cache

```php
'cache' => [
    'enabled' => true,
    'store' => env('GOOGLE_TRANSLATE_CACHE_STORE'),   // null = default store
    'ttl' => 60 * 60 * 24 * 30,                       // 30 days
    'prefix' => 'gtt',
],
```

### Rate limiting

```php
'rate_limit' => [
    'enabled' => false,
    'key' => 'google-translate-toolkit',
    'max_per_minute' => 600,
],
```

Uses Laravel's `RateLimiter`. When the ceiling is reached, the package throws
`RateLimitExceededException` **before** the request goes out, carrying
`$secondsUntilAvailable`.

### Resilience

```php
'fallback_to_source' => env('GOOGLE_TRANSLATE_FALLBACK_TO_SOURCE', false),
'events' => true,
```

### Queue

```php
'queue' => [
    'connection' => env('GOOGLE_TRANSLATE_QUEUE_CONNECTION'),
    'queue' => env('GOOGLE_TRANSLATE_QUEUE'),
],
```

### Eloquent

```php
'attribute_suffix' => '_{locale}',   // body + it => body_it
```

### Placeholders

```php
'placeholders' => [
    'enabled' => true,
    'patterns' => [],   // your own regexes or literal terms, merged with the built-ins
],
```

### Glossary

```php
'glossary' => [
    'protect' => ['Novias', 'Mailgun'],
    'overrides' => [
        'it' => ['bounce' => 'rifiuto'],
    ],
],
```

### Pricing, budget and statistics

```php
'pricing' => [
    'per_million' => 20.00,
    'currency' => 'USD',
],

'budget' => [
    'max_characters_per_day' => null,   // e.g. 500_000
],

'stats' => [
    'enabled' => true,
    'retention_days' => 30,
],
```

### Response middleware

```php
'middleware' => [
    'enabled' => false,
    'fields' => [],    // 'data.*.title'
    'locales' => [],   // restrict negotiation to these
],
```

## Core concepts

### The builder is immutable

Every modifier returns a **new** instance, so a configured builder is safe to
store, share and reuse:

```php
$italian = GoogleTranslate::from('en')->to('it')->preserving();

$italian->text('Hello');        // the builder is unchanged
$italian->asHtml()->text($html) // a different builder
```

### Terminal methods

A builder does nothing until you call a terminal method:

| Method | Returns |
|---|---|
| `translate($text)` | `Translation`, `TranslationCollection`, or a `Collection` keyed by locale |
| `text($string)` | `string` |
| `many($iterable)` | `TranslationCollection`, or a `Collection` keyed by locale |
| `lazy($iterable)` | `LazyCollection<Translation>` |
| `json($payload)` | `array` with the same shape |
| `detect($text)` | `DetectedLanguage` or a `Collection` of them |
| `roundTrip($text)` | `RoundTripResult` |
| `estimate($texts)` | `Estimate` — spends nothing |

### The return shape follows the input

| Input | One target | Several targets |
|---|---|---|
| A string | `Translation` | `Collection<locale, Translation>` |
| An array | `TranslationCollection` | `Collection<locale, TranslationCollection>` |

### Objects that behave like the old arrays

`Translation` and `DetectedLanguage` are readonly, but they implement
`ArrayAccess`, `Arrayable`, `Jsonable`, `JsonSerializable` and `Stringable`, with
the **1.x array keys** intact:

```php
$t = GoogleTranslate::translate('Hello');

$t->translatedText;         // property
$t['translated_text'];      // 1.x array key
(string) $t;                // "Ciao"
$t->toArray();              // source_text, source_language_code, translated_text, …
json_encode($t);            // the same, as JSON
```

## Translating

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslate;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;

// Defaults from the config
GoogleTranslate::justTranslate('Hello');

// Explicit, positional (the 1.x signature)
GoogleTranslate::justTranslate('Hello', from: 'en', to: 'it');

// Explicit, fluent
GoogleTranslate::from(Language::English)->to(Language::Italian)->text('Hello');

// HTML — tags are preserved and entities are left encoded
GoogleTranslate::asHtml()->text('<p>Hello <b>world</b></p>');

// A batch: one request, one order, one price
$batch = GoogleTranslate::translateBatch(['one', 'two', 'three']);
$batch->texts()->all();        // ['uno', 'due', 'tre']
$batch->dictionary()->all();   // ['one' => 'uno', 'two' => 'due', …]
$batch->toStrings();           // plain array of strings
```

### Text or HTML

`text` is the default. In HTML mode Google keeps your markup, so a paragraph of
formatted content comes back as a paragraph of formatted content — and entities
stay encoded, because re-decoding them would corrupt the markup.

```php
GoogleTranslate::asText()->text($plain);
GoogleTranslate::asHtml()->text($markup);
GoogleTranslate::format('html')->text($markup);   // same thing
```

In plain-text mode the package decodes the entities Google returns, so
`L&#39;italiano` comes back as `L'italiano`.

### Skipping work you do not need

```php
// Translate only if the text is not already Italian
GoogleTranslate::unlessLanguageIs('it', $text);

// Just ask
GoogleTranslate::isLanguage($text, 'it');   // bool
```

`unlessLanguageIs()` always returns a `Translation`; use `$result->isUnchanged()`
to tell "already in that language" from "translated".

## Detecting languages

```php
$detected = GoogleTranslate::detect('bonjour tout le monde');

$detected->languageCode;   // "fr"
$detected->confidence;     // 0.98
$detected->reliable;       // true
$detected->language();     // Language::French
$detected->is('fr');       // true
$detected['language_code'] // 1.x key

// A batch
GoogleTranslate::detect(['hello', 'ciao', 'bonjour'])
    ->pluck('languageCode')
    ->all();   // ['en', 'it', 'fr']
```

## Many languages at once

```php
$results = GoogleTranslate::to(['it', 'fr', 'de'])->translate('Good morning');

$results['it']->translatedText;   // "Buongiorno"
$results['fr']->translatedText;   // "Bonjour"
```

Every (chunk × target) request goes out through a single `Http::pool()`, bounded
by `http.concurrency`. Targets that share the same set of cache misses are
grouped into one call. Duplicate and differently-cased targets are collapsed, so
`['it', 'IT', 'it']` is one language.

For batches you get a collection of collections:

```php
$results = GoogleTranslate::to(['it', 'es'])->many(['one', 'two']);

$results['es']->texts()->all();   // ['uno', 'dos']
```

## Nested payloads

`translateJson()` walks an array or a JSON string, translates only the leaves you
select, and hands back the same structure:

```php
$payload = [
    'id' => 7,
    'title' => 'Spring collection',
    'meta' => ['slug' => 'spring-collection', 'description' => 'Our new season'],
    'items' => [
        ['title' => 'Silk dress', 'sku' => 'SD-1'],
        ['title' => 'Linen suit', 'sku' => 'LS-4'],
    ],
];

GoogleTranslate::translateJson(
    $payload,
    only: ['title', 'meta.description', 'items.*.title'],
    except: ['meta.slug'],
);
```

- Dot notation, wildcards (`items.*.title`), `only` defaults to `['*']`.
- Numbers, booleans, nulls and empty strings are never sent.
- Every selected leaf goes out in **one** batch, however deep it lives.

## Very large sets

```php
GoogleTranslate::to('it')
    ->lazy(SentEmail::query()->lazyById()->pluck('delivery_message'))
    ->each(function ($translation) {
        // one Translation at a time, constant memory
    });
```

`lazy()` chunks the source, pools each chunk, and yields `Translation` objects as
they arrive. It is single-target by design — fan-out plus streaming is a bill
waiting to happen, so it throws if you pass several targets.

## Placeholders

This is the part other packages get wrong. Google will happily translate
`:name`, reorder `%s`, break `{{ $blade }}` and put a space in the middle of a
URL. The package masks them before the call and puts them back after.

```php
GoogleTranslate::justTranslate('Welcome back, :name — you have {count} items at https://novias.it');
// "Bentornato, :name — hai {count} articoli su https://novias.it"
```

Ten patterns are shielded out of the box, greediest first:

| Pattern | Example |
|---|---|
| `<code>` blocks | `<code>php artisan</code>` |
| Backtick spans | `` `composer update` `` |
| Blade comments | `{{-- hidden --}}` |
| Blade / Handlebars | `{{ $user->name }}` |
| Curly placeholders | `{count}`, `{first_name}` |
| URLs | `https://novias.it/path?q=1` |
| E-mail addresses | `support@novias.it` |
| Laravel placeholders | `:name`, `:Name`, `:NAME` |
| printf | `%s`, `%2$d`, `%.2f` |
| Handles | `@gabrielesbaiz` |

Add your own, per call or globally:

```php
// Per call — regex or literal
GoogleTranslate::preserving(['/#[A-Za-z0-9_]+/', 'Novias Source'])->text($text);

// Globally
'placeholders' => ['patterns' => ['/\[\[.*?\]\]/']],
```

Turn it off when you know the text is plain prose:

```php
GoogleTranslate::query()->withoutPreserving()->text($text);
```

In plain-text mode the mask is `⟦0⟧`; in HTML mode it is
`<span translate="no">0</span>`, which Google is contractually obliged to leave
alone. Restoration is tolerant: if Google adds a space inside the sentinel, the
package still finds it.

## Glossary

Two different jobs, one config section.

**Protected terms** never reach Google at all — they are masked like a
placeholder:

```php
'glossary' => ['protect' => ['Novias', 'Mailgun', 'Postmark']],
```

```php
GoogleTranslate::justTranslate('Novias sent the message');
// "Novias ha inviato il messaggio"  — the brand is untouched
```

**Overrides** are applied *after* the translation comes back, so you can force
domain wording Google gets wrong:

```php
'glossary' => [
    'overrides' => [
        'it' => ['rimbalzo' => 'rifiuto', 'consegna fallita' => 'mancata consegna'],
    ],
],
```

Matching is case-insensitive and word-bounded, and the original capitalisation is
preserved: `Rimbalzo` becomes `Rifiuto`, `RIMBALZO` becomes `RIFIUTO`.

Skip the whole mechanism for one call:

```php
GoogleTranslate::withoutGlossary()->text($text);
```

## Caching

Translations are deterministic enough to cache, and caching them is the single
biggest thing this package does for your bill.

- The key is `sha1(source|target|format|text)` under the `cache.prefix`.
- A batch is **partitioned**: hits are served locally, and only the misses are sent.
- The supported-language list is cached for a day through `Cache::flexible()` where the store supports it, so an expiring entry is served instantly and refreshed in the background.
- `Translation::$cached` tells you where a value came from.

```php
GoogleTranslate::justTranslate('Hello');   // one API call
GoogleTranslate::justTranslate('Hello');   // no API call

GoogleTranslate::withoutCache()->text('Hello');   // always calls
GoogleTranslate::withCache(ttl: 3600)->text('Hello');
```

Invalidating is namespaced — it bumps an internal version counter rather than
flushing your application cache:

```php
GoogleTranslate::flushCache();
```

```bash
php artisan translate:cache-clear
```

Pre-fill it before a launch:

```php
GoogleTranslate::warm($strings, ['it', 'de', 'fr']);   // queued Bus::batch
```

## Resilience

Failure is a configuration decision, not an accident.

```php
// Throw (the default)
GoogleTranslate::justTranslate($text);

// Return the source text instead, and carry on
GoogleTranslate::onFailUseSource()->text($text);

// Or globally
'fallback_to_source' => true,
```

`onFailUseSource()` is the right setting for webhooks and queued jobs: a Google
outage should not fail a job whose real work is storing a bounce record. The
failure is still reported through the `TranslationFailed` event, with
`recovered: true`.

The exception hierarchy — everything extends `GoogleTranslateException`, which
extends `RuntimeException`:

| Exception | When |
|---|---|
| `MissingApiKeyException` | No key configured, thrown lazily on first call |
| `UnsupportedLanguageException` | Unknown code while `strict_languages` is on |
| `InvalidFormatException` | A format other than `text` or `html` |
| `TranslationFailedException` | Non-2xx response, transport error, malformed payload |
| `RateLimitExceededException` | Local rate limit reached; carries `$secondsUntilAvailable` |
| `BudgetExceededException` | The daily character budget would be exceeded |

Blade directives never throw: `@translate` falls back to the source text so a
Google outage cannot take a page down.

## Cost, budget and statistics

Google bills per million characters, per target language. Estimate first:

```php
$estimate = GoogleTranslate::estimate($texts, ['it', 'fr']);

$estimate->segments;              // 128
$estimate->characters;            // 8_402
$estimate->billableCharacters;    // 16_804  (× 2 targets)
$estimate->cachedSegments;        // 31 already in the cache
$estimate->requests;              // 4
$estimate->formattedCost();       // "USD 0.3361"
```

```bash
php artisan translate:cost --file=storage/strings.txt --to=it --to=fr
```

Set a ceiling and the package refuses to cross it — the exception is thrown
before the request is built, so nothing is billed:

```php
'budget' => ['max_characters_per_day' => 500_000],
```

Watch the spend:

```php
GoogleTranslate::stats(7);   // per day: calls, characters, cache hits, hit rate, failures, cost
```

```bash
php artisan translate:stats --days=30
```

Counters live in the cache store, keyed by day, kept for `stats.retention_days`.

## Quality assurance

Back-translation is the cheapest useful check there is: translate out, translate
back, and see how much meaning survived.

```php
$check = GoogleTranslate::roundTrip('The message was rejected', via: 'it');

$check->translated;          // "Il messaggio è stato rifiutato"
$check->back;                // "The message was rejected"
$check->score;               // 1.0
$check->isSuspicious(0.6);   // false
```

The score blends character overlap with normalised edit distance, so it is
robust for short strings and does not reward padding.

Audit a whole lang file:

```bash
php artisan translate:audit --from=en --to=it --threshold=0.7
```

It prints a table of every line scoring below the threshold, with the source, the
stored translation and the back-translation side by side. It costs one call per
line — the command tells you what that will be before it starts.

## Eloquent models

The convention is a sibling column per locale: `body` and `body_it`. That is the
`attribute_suffix` config, and it matches what most applications already do by
hand.

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Concerns\HasTranslations;
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translatable;
use Illuminate\Database\Eloquent\Model;

class SentEmail extends Model implements Translatable
{
    use HasTranslations;

    public function translatableAttributes(): array
    {
        return ['delivery_message'];
    }
}
```

```php
// Fill delivery_message_it from delivery_message
$email->translateAttributes()->save();

// A different locale, an explicit source, only some attributes
$email->translateAttributes(to: 'de', from: 'en', attributes: ['delivery_message'])->save();

// Retranslate something that already has a value
$email->translateAttributes(overwrite: true)->save();

// Off to the queue
$email->queueTranslateAttributes('it');

// Read it back
$email->getTranslatedAttribute('delivery_message', 'it');
$email->translatedAttributeName('delivery_message', 'it');   // "delivery_message_it"
```

Already-translated rows are skipped, so running it twice costs nothing.

Find what is still missing:

```php
SentEmail::query()->whereTranslationMissing('delivery_message')->count();
SentEmail::query()->whereTranslationMissing('delivery_message', 'de')->get();
```

Backfill a table:

```bash
php artisan translate:model "App\Models\SentEmail" --to=it --chunk=500
php artisan translate:model "App\Models\SentEmail" --to=it --queue --limit=1000
```

## Queues and deferred work

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Jobs\TranslateAttributesJob;
use Gabrielesbaiz\GoogleTranslateToolkit\Jobs\WarmTranslationCacheJob;

TranslateAttributesJob::dispatch($email, 'it');
WarmTranslationCacheJob::dispatch($strings, ['it', 'fr']);
```

Both honour `queue.connection` and `queue.queue`.
`TranslateAttributesJob::uniqueId()` is keyed by model and locale, so adding
`ShouldBeUnique` in your own subclass is enough to collapse duplicates.

`deferred()` uses Laravel's `defer()` to run the translation **after** the
response has been sent — the right tool for a webhook endpoint that must answer
in milliseconds but still wants the translation:

```php
GoogleTranslate::deferred()->translate($message, function ($translation) use ($email) {
    $email->update(['delivery_message_it' => $translation->translatedText]);
});
```

In the console and in tests it runs inline, so nothing is silently skipped.

## Blade, macros and helpers

```blade
@translate($post->title)                {{-- default target --}}
@translate($post->title, 'fr')          {{-- to French --}}
@translate($post->title, 'fr', 'en')    {{-- from English to French --}}

@translateHtml($post->body, 'fr')       {{-- markup in, markup out, unescaped --}}
```

Target first, source second. **1.x had these swapped**, so `@translate($x, 'it')`
translated *from* Italian; if you compensated for that, undo it.

`@translate` escapes its output and falls back to the source text on failure.
`@translateHtml` does not escape — only give it markup you trust.

```php
Str::translate('hello');                 // "ciao"
Str::translate('hello', 'fr', 'en');     // to French, from English

str('hello')->translate()->upper();      // Stringable, chainable
str('hello')->translateTo('de');
str('bonjour')->detectLanguage();        // "fr"

collect(['a', 'b'])->translate('fr');    // TranslationCollection

google_translate('hello', 'es');         // Translation
google_translate();                      // the toolkit itself
```

Every macro is registered only if the name is free, so it cannot collide with
your own.

## Validation and casting

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Rules\LanguageRule;
use Illuminate\Validation\Rule;

$request->validate([
    'locale' => ['required', Rule::language()],                  // any supported code
    'target' => ['required', Rule::language(['it', 'en', 'fr'])], // a shortlist
    'other'  => ['required', new LanguageRule(['it'])],           // without the macro
]);
```

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Casts\AsLanguage;

protected function casts(): array
{
    return ['locale' => AsLanguage::class];
}

$model->locale;              // Language::Italian
$model->locale = 'ZH_tw';    // stored as "zh-TW"
```

The `Language` enum is useful on its own:

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;

Language::Italian->value;              // "it"
Language::ChineseTraditional->label(); // "Chinese (Traditional)"
Language::Arabic->isRtl();             // true
Language::normalize('ZH_tw');          // "zh-TW"
Language::tryFromCode('pt-BR');        // Language::Portuguese
Language::options();                   // Collection<code, label> — ready for a <select>
Language::codes();                     // array of all 137 codes
```

## Response middleware

Off by default, and deliberately so: it translates on the hot path and every
uncached field costs money.

```php
'middleware' => [
    'enabled' => true,
    'fields' => ['data.*.title', 'data.*.excerpt'],
    'locales' => ['it', 'fr', 'de'],
],
```

```php
Route::middleware('translate.response')->get('/posts', PostController::class);

// or per route, overriding the configured fields
Route::middleware('translate.response:data.*.title')->get('/posts', PostController::class);
```

It only touches successful JSON responses, negotiates the language from
`Accept-Language`, skips the work entirely when the visitor already speaks the
application locale, and falls back to the untranslated response if Google fails.

## Artisan commands

| Command | Purpose |
|---|---|
| `translate:text` | Translate strings from the console |
| `translate:languages` | List supported languages |
| `translate:lang` | Translate lang files into other locales |
| `translate:model` | Backfill a model's translated columns |
| `translate:audit` | Back-translate existing lang lines and flag the weak ones |
| `translate:cost` | Price a translation before spending |
| `translate:stats` | Volume, cache hit rate and estimated spend |
| `translate:cache-clear` | Invalidate everything the package cached |

### `translate:text`

```bash
php artisan translate:text "Hello world" "Good evening" --to=it --to=fr
php artisan translate:text "Hello" --from=en --to=de --format=html --no-cache
php artisan translate:text "Hello" --to=it --json
php artisan translate:text "Hello" --to=it --dry-run     # price only
```

| Flag | Meaning |
|---|---|
| `--from=` | Source code; omitted means auto-detect |
| `--to=*` | One or more targets |
| `--format=` | `text` (default) or `html` |
| `--no-cache` | Bypass the cache |
| `--dry-run` | Report the cost, send nothing |
| `--json` | Raw JSON output |

### `translate:languages`

```bash
php artisan translate:languages
php artisan translate:languages --target=it --search=chin
php artisan translate:languages --offline
```

| Flag | Meaning |
|---|---|
| `--target=` | Language to display the names in |
| `--search=` | Filter by code or name |
| `--offline` | Use the bundled list, no API call |

### `translate:lang`

```bash
php artisan translate:lang --from=en --to=it --to=fr
php artisan translate:lang --from=en --to=it --group=auth --group=validation
php artisan translate:lang --from=en --to=it --dry-run
php artisan translate:lang --from=en --to=it --force
```

Reads `lang/{from}/*.php` and `lang/{from}.json`, translates only the lines the
target does not have yet, and writes properly formatted PHP and JSON back.
Placeholder protection is on, so `:attribute` and `{count}` survive. Nested
arrays keep their structure; keys are sorted; `__json` is the group name for the
JSON file.

| Flag | Meaning |
|---|---|
| `--from=` | Source locale (default `en`) |
| `--to=*` | Target locales |
| `--group=*` | Only these files |
| `--force` | Overwrite lines that already exist |
| `--dry-run` | Count and price, write nothing |

### `translate:model`

```bash
php artisan translate:model "App\Models\SentEmail" --to=it
php artisan translate:model "App\Models\Post" --to=de --attributes=title --attributes=body --chunk=500
php artisan translate:model "App\Models\Post" --to=de --queue --limit=2000
```

| Flag | Meaning |
|---|---|
| `--to=` / `--from=` | Target and source locales |
| `--attributes=*` | Limit to these attributes |
| `--chunk=` | Rows per chunk (default 200) |
| `--limit=` | Stop after this many rows |
| `--queue` | Dispatch a job per row instead of translating inline |
| `--overwrite` | Retranslate rows that already have a value |

### `translate:audit`

```bash
php artisan translate:audit --from=en --to=it --threshold=0.7 --limit=200
```

### `translate:cost`

```bash
php artisan translate:cost "Some text" --to=it --to=fr
php artisan translate:cost --file=storage/app/strings.txt --to=it
```

### `translate:stats`

```bash
php artisan translate:stats --days=30
```

### `translate:cache-clear`

```bash
php artisan translate:cache-clear
```

## Testing

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslate;

$fake = GoogleTranslate::fake();

$this->postJson('/webhooks/postmark', $payload)->assertOk();

$fake->assertTranslated('The recipient rejected the message')
     ->assertTranslatedTo('it')
     ->assertTranslatedCount(1);
```

Without stubs the fake returns `"[it] the original text"`, which keeps
assertions readable. Stub what matters:

```php
GoogleTranslate::fake([
    'Hello' => 'Ciao',                                   // any target
    'Bye' => ['it' => 'Ciao ciao', 'fr' => 'Au revoir'], // per target
    'Dynamic' => fn ($text, $target) => "$target:$text", // computed
])->detectAs('fr');
```

| Assertion | Checks |
|---|---|
| `assertTranslated($text\|$closure, ?$target)` | The text was translated |
| `assertNotTranslated($text)` | It was not |
| `assertNothingTranslated()` | No translation happened at all |
| `assertTranslatedCount($n)` | Exactly `$n` translation calls |
| `assertTranslatedTo($locale)` | Something was translated into that locale |
| `assertDetected(?$text)` | Detection ran, optionally for that text |

Inspect the recording directly with `recorded()`, `recordedDetections()` and
`translatedTexts()`.

Prefer to assert on the wire? The package is a plain `Http` client, so
`Http::fake()` and `Http::assertSent()` work exactly as you expect — that is how
this package's own suite is written.

## Events

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Events\TranslationCompleted;
use Gabrielesbaiz\GoogleTranslateToolkit\Events\TranslationFailed;
```

| Event | Properties |
|---|---|
| `TranslationCompleted` | `translations`, `target`, `source`, `characters`, `cacheHits`, `requests` |
| `TranslationFailed` | `exception`, `texts`, `target`, `source`, `recovered` |

Set `'events' => false` to silence both.

```php
Event::listen(TranslationFailed::class, function (TranslationFailed $event) {
    Log::warning('Translation failed', [
        'target' => $event->target,
        'recovered' => $event->recovered,
        'message' => $event->exception->getMessage(),
    ]);
});
```

## Extending

### A driver of your own

Bind anything that implements the `Translator` contract — a different provider,
an on-premise model, a canned dataset for a demo environment:

```php
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;

$this->app->singleton(Translator::class, DeepLTranslator::class);
```

```php
interface Translator
{
    public function translate(array $texts, ?string $source, string $target, TextFormat $format): array;
    public function translateMany(array $texts, ?string $source, array $targets, TextFormat $format): array;
    public function detect(array $texts): array;
    public function languages(string $displayLanguage): array;
}
```

Everything else — cache, placeholders, glossary, budget, events, Eloquent,
commands — keeps working on top of it.

### Macros

Both `GoogleTranslateToolkit` and `PendingTranslation` are `Macroable`, and both
are `Conditionable`:

```php
GoogleTranslateToolkit::macro('toCustomers', fn (string $text) => $this->to(config('app.customer_locale'))->text($text));

GoogleTranslate::query()
    ->when($user->prefersFrench, fn ($q) => $q->to('fr'))
    ->text($text);
```

## API reference

### `GoogleTranslateToolkit` (facade: `GoogleTranslate`)

| Method | Description |
|---|---|
| `query()` | A fresh builder with the configured defaults |
| `from($language)` | Set the source language, or `null` to auto-detect |
| `to($language\|$languages)` | One or more targets |
| `format($format)` / `asText()` / `asHtml()` | Output format |
| `withCache(?$ttl)` / `withoutCache()` | Cache behaviour |
| `onFailUseSource(bool)` | Return the source text on failure |
| `preserving(array)` / `withoutGlossary()` | Placeholder and glossary control |
| `deferred(bool)` | Run after the response is sent |
| `translate($text, $from, $to, $format)` | Translate a string or an iterable |
| `justTranslate($text, $from, $to)` | The translated string only |
| `translateBatch($texts, $from, $to, $format)` | Batch translation |
| `translateJson($payload, $only, $except, $from, $to)` | Nested payloads |
| `lazy($texts, $from, $to, $chunkSize)` | Streaming |
| `unlessLanguageIs($code, $text, $from, $to)` | Translate only when needed |
| `isLanguage($text, $language)` | Detection as a boolean |
| `detect($text)` / `detectLanguage()` / `detectLanguageBatch()` | Language detection |
| `languages($display)` | Languages Google supports, named, cached for a day |
| `supportedLanguages()` | The bundled enum, no API call |
| `getAvailableTranslationsFor($code)` | 1.x alias of `languages()` |
| `sanitizeLanguageCode($code)` | Normalise and validate a code |
| `roundTrip($text, $via, $from)` | Back-translation QA |
| `estimate($texts, $targets)` | Cost estimate, spends nothing |
| `usage()` / `stats($days)` | Usage accounting |
| `warm($texts, $targets, $from)` | Queued cache seeding |
| `flushCache()` | Invalidate the package's cache namespace |
| `blade()` / `bladeHtml()` | Backing calls for the directives |
| `fake($stubs)` | Swap in the fake translator |
| `translator()` | The resolved driver |

### `PendingTranslation`

Modifiers: `from`, `detectSource`, `to`, `format`, `asText`, `asHtml`,
`withCache`, `withoutCache`, `onFailUseSource`, `onFailThrow`, `preserving`,
`withoutPreserving`, `withoutGlossary`, `deferred`, plus `when`/`unless`.

Terminals: `translate`, `text`, `many`, `lazy`, `json`, `detect`, `roundTrip`,
`estimate`. Inspection: `targets()`, `options()`.

### Data objects

| Class | Members |
|---|---|
| `Translation` | `sourceText`, `translatedText`, `sourceLanguage`, `targetLanguage`, `format`, `detected`, `cached`; `sourceLanguage()`, `targetLanguage()`, `isUnchanged()`, `toArray()`, `toJson()` |
| `DetectedLanguage` | `text`, `languageCode`, `confidence`, `reliable`; `language()`, `is()` |
| `TranslationCollection` | `texts()`, `dictionary()`, `toStrings()`, plus everything on `Collection` |
| `Estimate` | `segments`, `characters`, `billableCharacters`, `cachedSegments`, `requests`, `targets`, `cost`, `currency`; `formattedCost()` |
| `RoundTripResult` | `source`, `translated`, `back`, `sourceLanguage`, `pivotLanguage`, `score`; `isSuspicious()` |

### Enums

`Language` — 137 cases, 9 right-to-left. `normalize()`, `tryFromCode()`,
`fromCode()`, `codes()`, `options()`, `label()`, `isRtl()`.

`TextFormat` — `Text`, `Html`. `make()`, `isHtml()`.

## Recipes

### A webhook that must never fail

```php
$sentEmail = SentEmail::query()->updateOrCreate(['id' => $payload->get('MessageID')], [
    'delivery_message' => $payload->get('Description'),
    'delivery_message_it' => GoogleTranslate::onFailUseSource()->text($payload->get('Description')),
]);
```

Or push it past the response entirely:

```php
GoogleTranslate::deferred()->translate($description, fn ($t) => $sentEmail->update([
    'delivery_message_it' => $t->translatedText,
]));
```

### Translating a whole application's lang files

```bash
php artisan translate:lang --from=en --to=it --to=fr --to=de --dry-run
php artisan translate:lang --from=en --to=it --to=fr --to=de
php artisan translate:audit --to=it --threshold=0.65
```

### Keeping an API resource multilingual

```php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return GoogleTranslate::to($request->user()->locale)->json([
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'slug' => $this->slug,
        ], only: ['title', 'body']);
    }
}
```

### Nightly backfill

```php
// routes/console.php
Schedule::command('translate:model', ['App\Models\SentEmail', '--to' => 'it', '--queue'])
    ->dailyAt('02:00');
```

## Troubleshooting

**`MissingApiKeyException`** — `GOOGLE_TRANSLATE_API_KEY` is unset, or the config
is cached from before you set it. Run `php artisan config:clear`.

**`API key not valid`** — the key exists but the Cloud Translation API is not
enabled on that project, or a key restriction is blocking it.

**`UnsupportedLanguageException` for a code Google supports** — the enum is
behind. Set `'strict_languages' => false`, and please open an issue.

**Placeholders still come back broken** — the pattern is not one of the ten
built-ins. Add it with `->preserving(['/your-pattern/'])` or in
`placeholders.patterns`. Note that literal terms are quoted automatically;
anything that looks like `/…/` is treated as a regex.

**Translations do not update after I changed the glossary** — the old value is
cached. `php artisan translate:cache-clear`.

**The same string is translated twice** — check `cache.enabled`, and remember
that `format` and `source` are part of the cache key: `text` and `html` are
different entries.

**Tests hit the network** — call `GoogleTranslate::fake()` or `Http::fake()`.
`Http::preventStrayRequests()` in your `TestCase` turns any leak into a failure.

## Upgrading from 1.x

Read [UPGRADING.md](UPGRADING.md). The short version:

- Your calls keep working; return values are objects that array-access with the 1.x keys.
- The published 1.x config is still read (`default_source_translation`, `default_target_translation`, `GOOGLE_DEVELOPER_KEY`).
- `composer remove google/cloud-translate` once nothing else needs it.
- **`@translate($text, 'it')` used to translate *from* Italian.** It now translates *to* Italian.
- `unlessLanguageIs()` always returns a `Translation`.

## Testing the package

```bash
composer test        # Pest — 93 tests, no network
composer analyse     # PHPStan level 6
composer format      # Pint
```

## Contributing

Pull requests are welcome. Please keep the suite green, PHPStan clean and Pint
happy — those three commands are the contract.

## Security vulnerabilities

Please review [our security policy](../../security/policy) on how to report
security vulnerabilities. Please do not open a public issue.

## Credits

Written and maintained by [Gabriele Sbaiz](https://github.com/gabrielesbaiz).

The 1.x line began as a fork of
[JoggApp/laravel-google-translate](https://github.com/JoggApp/laravel-google-translate)
by [Jogg](https://github.com/Jogg). 2.0 is a rewrite, and the debt is gladly
acknowledged.

Built on Laravel and
[spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools).

## Support this package

If it is useful to you:

- ⭐ **Star the repo.** Free, thirty seconds, and it is the first signal other developers look at.
- ❤️ **[Become a sponsor](https://github.com/sponsors/gabrielesbaiz).** From $5 a month.
- 🐛 **Open a good issue.** A clear reproduction is worth more than you think.
- 🗣️ **Tell another Laravel developer.** Word of mouth is how packages survive.

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-gabrielesbaiz-ff69b4?style=for-the-badge&logo=github-sponsors)](https://github.com/sponsors/gabrielesbaiz)

## Disclaimer

This package is provided **as is**, without warranty of any kind, express or
implied, including but not limited to the warranties of merchantability, fitness
for a particular purpose, title and non-infringement. To the fullest extent
permitted by applicable law, in no event shall the authors, copyright holders or
contributors be liable for any claim, damages or other liability — whether in an
action of contract, tort or otherwise — arising from, out of or in connection
with this package or its use, including without limitation any direct, indirect,
incidental, special, exemplary, consequential or punitive damages, loss of data,
loss of profits, business interruption, or unexpected charges from a third-party
API.

This package sends your text to Google. Whoever deploys it is responsible for
deciding whether that is acceptable for the data in question, and for meeting
whatever regulatory, contractual or privacy obligations apply — including, and
not limited to, personal data, confidential business information and anything
covered by the GDPR. Machine translation is not a substitute for a professional
translator where accuracy carries legal or safety consequences. Cost estimates
are estimates: the authoritative figure is the one on your Google Cloud invoice.
Nothing here constitutes legal, compliance or security advice.

Use of this package is entirely at your own risk.

## License

MIT. See [LICENSE.md](LICENSE.md). The MIT licence's warranty disclaimer and
limitation of liability apply in full, alongside the disclaimer above.
