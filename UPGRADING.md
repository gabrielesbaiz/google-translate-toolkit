# Upgrading from 1.x to 2.0

2.0 is a rewrite, but the calls your application already makes keep working.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Google SDK removed

`google/cloud-translate` is gone; the package now uses Laravel's HTTP client against the v2 REST endpoint. If nothing
else in your application needs it, remove it:

```bash
composer remove google/cloud-translate
```

The API key is sent as an `X-goog-api-key` header instead of a query parameter, so it no longer shows up in proxy logs.

## Configuration

Republish the config to get the new sections:

```bash
php artisan vendor:publish --tag="google-translate-toolkit-config" --force
```

The 1.x keys are still read, so an un-republished config keeps working:

| 1.x | 2.0 |
| --- | --- |
| `default_source_translation` | `default_source` (now nullable: `null` = auto-detect) |
| `default_target_translation` | `default_target` (falls back to `app.locale`) |
| `api_key` | unchanged |

Note the changed default: leaving `default_source` empty lets Google detect the source language, which is cheaper and
more accurate than forcing `en`.

## Return values

Methods return typed objects instead of plain arrays. They implement `ArrayAccess` with the **1.x keys**, are
`Arrayable`, `Jsonable` and `Stringable`, so existing code keeps reading:

```php
$result = GoogleTranslateToolkit::translate('Hello');

$result['translated_text'];          // as in 1.x
$result['source_language_code'];     // as in 1.x
$result->translatedText;             // new
(string) $result;                    // new
```

| Method | 1.x | 2.0 |
| --- | --- | --- |
| `translate()` | `array` | `Translation` (or `TranslationCollection` / keyed `Collection`) |
| `justTranslate()` | `string` | `string` — unchanged |
| `detectLanguage()` | `array` | `DetectedLanguage` |
| `detectLanguageBatch()` | `array` | `Collection<DetectedLanguage>` |
| `translateBatch()` | `array` | `TranslationCollection` |
| `getAvailableTranslationsFor()` | `array` | `Collection<code, name>` |
| `unlessLanguageIs()` | `string|array` | always `Translation` |

`unlessLanguageIs()` is the one behavioural change: it used to return the raw string when the language already matched
and an array otherwise. It now always returns a `Translation`; use `$result->isUnchanged()` to tell the cases apart.

## Blade directive fixed

In 1.x, `@translate($text, 'it')` passed `'it'` into the **source** slot, so the text was translated *from* Italian.
The directive now reads target first, source second:

```blade
@translate($text)              {{-- default target --}}
@translate($text, 'it')        {{-- to Italian --}}
@translate($text, 'it', 'en')  {{-- from English to Italian --}}
```

If you compensated for the old bug by swapping the arguments, undo that.

## Language codes

Codes are normalised (`ZH_tw` → `zh-TW`) and validated against a complete enum. Unknown codes throw
`UnsupportedLanguageException`; set `strict_languages` to `false` to pass any code straight through.

## Exceptions

Everything now extends `GoogleTranslateException` (a `RuntimeException`):
`MissingApiKeyException`, `UnsupportedLanguageException`, `InvalidFormatException`, `TranslationFailedException`,
`RateLimitExceededException`, `BudgetExceededException`.

Catching `\Exception` still works.

## Caching is on by default

Translations are cached for 30 days keyed by source, target, format and text. Disable globally with `cache.enabled`,
per call with `->withoutCache()`, and flush with `php artisan translate:cache-clear`.

## Worth adopting after the upgrade

- `'fallback_to_source' => true` in queued jobs and webhooks, so a Google outage never fails the job
- `glossary.protect` for brand names
- the `HasTranslations` trait plus `translate:model` instead of hand-written `*_it` writes
