<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation query()
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation from(\Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language|string|null $language)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation to(\Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language|string|iterable<int, \Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language|string> $language)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation format(\Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat|string $format)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation asHtml()
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation asText()
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation withCache(?int $ttl = null)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation withoutCache()
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation onFailUseSource(bool $fallback = true)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation preserving(array<int, string> $patterns = [])
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation withoutGlossary()
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\PendingTranslation deferred(bool $deferred = true)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation|\Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection|\Illuminate\Support\Collection<string, mixed> translate(string|iterable<array-key, string> $text, mixed $from = null, mixed $to = null, mixed $format = null)
 * @method static string justTranslate(string $text, mixed $from = null, mixed $to = null)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection|\Illuminate\Support\Collection<string, mixed> translateBatch(iterable<array-key, string> $texts, mixed $from = null, mixed $to = null, mixed $format = null)
 * @method static array<array-key, mixed> translateJson(array<array-key, mixed>|string $payload, array<int, string> $only = ['*'], array<int, string> $except = [], mixed $from = null, mixed $to = null)
 * @method static \Illuminate\Support\LazyCollection<int, \Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation> lazy(iterable<array-key, string> $texts, mixed $from = null, mixed $to = null, ?int $chunkSize = null)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation unlessLanguageIs(mixed $languageCode, string $text, mixed $from = null, mixed $to = null)
 * @method static bool isLanguage(string $text, mixed $language)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage|\Illuminate\Support\Collection<array-key, \Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage> detect(string|iterable<array-key, string> $text)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage|\Illuminate\Support\Collection<array-key, \Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage> detectLanguage(string|iterable<array-key, string> $input)
 * @method static \Illuminate\Support\Collection<array-key, \Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage> detectLanguageBatch(iterable<array-key, string> $input)
 * @method static \Illuminate\Support\Collection<string, string> languages(mixed $displayLanguage = null)
 * @method static \Illuminate\Support\Collection<string, string> supportedLanguages()
 * @method static \Illuminate\Support\Collection<string, string> getAvailableTranslationsFor(mixed $languageCode)
 * @method static string sanitizeLanguageCode(mixed $languageCode)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\RoundTripResult roundTrip(string $text, mixed $via = null, mixed $from = null)
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Data\Estimate estimate(string|iterable<array-key, string> $texts, array<int, string> $targets = [])
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Support\Usage usage()
 * @method static \Illuminate\Support\Collection<string, array<string, float|int>> stats(int $days = 7)
 * @method static \Illuminate\Bus\Batch warm(iterable<array-key, string> $texts, array<int, string> $targets = [], mixed $from = null)
 * @method static int flushCache()
 * @method static string blade(string $text, mixed $to = null, mixed $from = null, mixed $format = 'text')
 * @method static \Gabrielesbaiz\GoogleTranslateToolkit\Drivers\FakeTranslator fake(array<string, mixed> $stubs = [])
 *
 * @see \Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit
 */
class GoogleTranslateToolkit extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit::class;
    }
}
