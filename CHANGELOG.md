# Changelog

All notable changes to `google-translate-toolkit` will be documented in this file.

## 2.0.0

Full rewrite for Laravel 12/13 and PHP 8.3+.

### Added

- Fluent, immutable builder: `from()`, `to()`, `format()`, `asHtml()`, `withCache()`, `withoutCache()`,
  `onFailUseSource()`, `preserving()`, `withoutGlossary()`, `deferred()`
- Multi-target fan-out through a bounded `Http::pool()`
- Translation cache with partial batch hits, `Cache::flexible()` support and `translate:cache-clear`
- Placeholder protection for `:name`, `{count}`, `{{ blade }}`, `%s`, URLs, e-mails, handles and code spans
- Glossary: protected terms and per-locale forced wording
- `roundTrip()` back-translation scoring and the `translate:audit` command
- `estimate()`, `pricing`/`budget` config, `translate:cost` and `translate:stats`
- `warm()` queued cache seeding
- `translateJson()` for nested payloads, `lazy()` for large sets
- `HasTranslations` trait + `Translatable` contract, `whereTranslationMissing()`, `translate:model`,
  `TranslateAttributesJob`
- `Str::translate()`, `Stringable::translate()/translateTo()/detectLanguage()`, `Collection::translate()`,
  `google_translate()` helper
- `@translateHtml` directive, `Rule::language()`, `AsLanguage` cast, opt-in `translate.response` middleware
- `GoogleTranslate::fake()` with assertions
- `Language` and `TextFormat` enums, typed exceptions, retry with backoff, optional rate limiting
- Commands: `translate:text`, `translate:languages`, `translate:lang`, `translate:model`, `translate:audit`,
  `translate:cost`, `translate:stats`, `translate:cache-clear`

### Changed

- Transport is Laravel's HTTP client; `google/cloud-translate` is no longer required
- The API key travels in the `X-goog-api-key` header instead of the query string
- `default_source` may be null, enabling Google's auto-detection (1.x always sent a source language)
- Typed return objects that still array-access with the 1.x keys
- `unlessLanguageIs()` always returns a `Translation`

### Fixed

- `@translate($text, 'it')` passed the target language into the source slot in 1.x
- `zh-TW` could not be used as the configured default target (`ctype_lower` check)
- No timeout, retry or cache meant every call hit the API and a single blip failed the caller

## 1.0.0

- Initial release
