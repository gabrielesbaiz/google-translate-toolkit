<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\Estimate;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\RoundTripResult;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\Drivers\FakeTranslator;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Gabrielesbaiz\GoogleTranslateToolkit\Jobs\WarmTranslationCacheJob;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Config;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationCache;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationOptions;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationRunner;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Usage;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

class GoogleTranslateToolkit
{
    use Conditionable;
    use Macroable;

    public function __construct(
        protected readonly TranslationRunner $runner,
        protected readonly Config $config,
        protected readonly Usage $usage,
        protected readonly TranslationCache $cache,
        protected readonly Container $container,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Fluent entry points
    |--------------------------------------------------------------------------
    */

    public function query(): PendingTranslation
    {
        return new PendingTranslation($this->runner, $this->config, $this->usage, new TranslationOptions(
            source: $this->config->defaultSource(),
            targets: [$this->config->defaultTarget()],
            format: TextFormat::make($this->config->defaultFormat()),
        ));
    }

    public function from(Language|string|null $language): PendingTranslation
    {
        return $this->query()->from($language);
    }

    /** @param Language|string|iterable<int, Language|string> $language */
    public function to(Language|string|iterable $language): PendingTranslation
    {
        return $this->query()->to($language);
    }

    public function format(TextFormat|string $format): PendingTranslation
    {
        return $this->query()->format($format);
    }

    public function asHtml(): PendingTranslation
    {
        return $this->query()->asHtml();
    }

    public function asText(): PendingTranslation
    {
        return $this->query()->asText();
    }

    public function withCache(?int $ttl = null): PendingTranslation
    {
        return $this->query()->withCache($ttl);
    }

    public function withoutCache(): PendingTranslation
    {
        return $this->query()->withoutCache();
    }

    public function onFailUseSource(bool $fallback = true): PendingTranslation
    {
        return $this->query()->onFailUseSource($fallback);
    }

    /** @param array<int, string> $patterns */
    public function preserving(array $patterns = []): PendingTranslation
    {
        return $this->query()->preserving($patterns);
    }

    public function withoutGlossary(): PendingTranslation
    {
        return $this->query()->withoutGlossary();
    }

    public function deferred(bool $deferred = true): PendingTranslation
    {
        return $this->query()->deferred($deferred);
    }

    /*
    |--------------------------------------------------------------------------
    | Translating
    |--------------------------------------------------------------------------
    */

    /**
     * @param  string|iterable<array-key, string>  $text
     * @param  Language|string|iterable<int, Language|string>|null  $to
     * @return Translation|TranslationCollection|Collection<string, Translation|TranslationCollection>
     */
    public function translate(
        string|iterable $text,
        Language|string|null $from = null,
        Language|string|iterable|null $to = null,
        TextFormat|string|null $format = null,
    ): Translation|TranslationCollection|Collection {
        return $this->build($from, $to, $format)->translate($text);
    }

    /**
     * The translated string, nothing else. Kept from 1.x.
     *
     * @param  Language|string|iterable<int, Language|string>|null  $to
     */
    public function justTranslate(string $text, Language|string|null $from = null, Language|string|iterable|null $to = null): string
    {
        return $this->build($from, $to)->text($text);
    }

    /**
     * @param  iterable<array-key, string>  $texts
     * @param  Language|string|iterable<int, Language|string>|null  $to
     * @return TranslationCollection|Collection<string, TranslationCollection>
     */
    public function translateBatch(
        iterable $texts,
        Language|string|null $from = null,
        Language|string|iterable|null $to = null,
        TextFormat|string|null $format = null,
    ): TranslationCollection|Collection {
        return $this->build($from, $to, $format)->many($texts);
    }

    /**
     * @param  array<array-key, mixed>|string  $payload
     * @param  array<int, string>  $only
     * @param  array<int, string>  $except
     * @param  Language|string|iterable<int, Language|string>|null  $to
     * @return array<array-key, mixed>
     */
    public function translateJson(
        array|string $payload,
        array $only = ['*'],
        array $except = [],
        Language|string|null $from = null,
        Language|string|iterable|null $to = null,
    ): array {
        return $this->build($from, $to)->json($payload, $only, $except);
    }

    /**
     * @param  iterable<array-key, string>  $texts
     * @return LazyCollection<int, Translation>
     */
    public function lazy(
        iterable $texts,
        Language|string|null $from = null,
        Language|string|null $to = null,
        ?int $chunkSize = null,
    ): LazyCollection {
        return $this->build($from, $to)->lazy($texts, $chunkSize);
    }

    /**
     * Translate only when the text is not already in the given language.
     * Unlike 1.x this always returns a Translation, so the return type is stable.
     */
    public function unlessLanguageIs(
        Language|string $languageCode,
        string $text,
        Language|string|null $from = null,
        Language|string|null $to = null,
    ): Translation {
        $detected = $this->detect($text);
        $code = Language::normalize($languageCode);

        if ($detected->is($code)) {
            return new Translation(
                sourceText: $text,
                translatedText: $text,
                sourceLanguage: $detected->languageCode,
                targetLanguage: $code,
                format: TextFormat::make($this->config->defaultFormat()),
                detected: true,
            );
        }

        /** @var Translation $translation */
        $translation = $this->build($from ?? $detected->languageCode, $to)->translate($text);

        return $translation;
    }

    public function isLanguage(string $text, Language|string $language): bool
    {
        return $this->detect($text)->is($language);
    }

    /*
    |--------------------------------------------------------------------------
    | Detecting
    |--------------------------------------------------------------------------
    */

    /**
     * @param  string|iterable<array-key, string>  $text
     * @return DetectedLanguage|Collection<array-key, DetectedLanguage>
     */
    public function detect(string|iterable $text): DetectedLanguage|Collection
    {
        return $this->query()->detect($text);
    }

    /**
     * @param  string|iterable<array-key, string>  $input
     * @return DetectedLanguage|Collection<array-key, DetectedLanguage>
     */
    public function detectLanguage(string|iterable $input): DetectedLanguage|Collection
    {
        return $this->detect($input);
    }

    /**
     * @param  iterable<array-key, string>  $input
     * @return Collection<array-key, DetectedLanguage>
     */
    public function detectLanguageBatch(iterable $input): Collection
    {
        /** @var Collection<array-key, DetectedLanguage> $detections */
        $detections = $this->detect(collect($input)->values()->all());

        return $detections;
    }

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    */

    /**
     * Languages Google can translate, named in the given display language.
     *
     * @return Collection<string, string>
     */
    public function languages(Language|string|null $displayLanguage = null): Collection
    {
        return $this->runner->languages($displayLanguage === null ? null : Language::normalize($displayLanguage));
    }

    /**
     * The offline list baked into the package - no API call.
     *
     * @return Collection<string, string>
     */
    public function supportedLanguages(): Collection
    {
        return Language::options();
    }

    /**
     * @return Collection<string, string>
     */
    public function getAvailableTranslationsFor(Language|string $languageCode): Collection
    {
        return $this->languages($languageCode);
    }

    public function sanitizeLanguageCode(Language|string $languageCode): string
    {
        return $this->config->strictLanguages()
            ? Language::fromCode($languageCode)->value
            : Language::normalize($languageCode);
    }

    /*
    |--------------------------------------------------------------------------
    | Quality, cost and operations
    |--------------------------------------------------------------------------
    */

    public function roundTrip(string $text, Language|string|null $via = null, Language|string|null $from = null): RoundTripResult
    {
        return $this->build($from, $via)->roundTrip($text, $via);
    }

    /**
     * @param  string|iterable<array-key, string>  $texts
     * @param  array<int, string>  $targets
     */
    public function estimate(string|iterable $texts, array $targets = []): Estimate
    {
        return $this->build(null, $targets === [] ? null : $targets)->estimate($texts);
    }

    public function usage(): Usage
    {
        return $this->usage;
    }

    /**
     * @return Collection<string, array<string, int|float>>
     */
    public function stats(int $days = 7): Collection
    {
        return $this->usage->forDays($days);
    }

    /**
     * Pre-translate a set of strings so runtime requests are always cache hits.
     *
     * @param  iterable<array-key, string>  $texts
     * @param  array<int, string>  $targets
     */
    public function warm(iterable $texts, array $targets = [], Language|string|null $from = null): Batch
    {
        $targets = $targets === [] ? [$this->config->defaultTarget()] : $targets;
        $source = $from === null ? $this->config->defaultSource() : Language::normalize($from);

        $jobs = LazyCollection::make($texts)
            ->map(fn (mixed $text): string => (string) $text)
            ->chunk($this->config->maxSegments())
            ->map(fn (LazyCollection $chunk) => new WarmTranslationCacheJob($chunk->values()->all(), $targets, $source))
            ->all();

        $batch = Bus::batch($jobs)->name('google-translate-warm');

        if ($connection = $this->config->queueConnection()) {
            $batch->onConnection($connection);
        }

        if ($queue = $this->config->queueName()) {
            $batch->onQueue($queue);
        }

        return $batch->dispatch();
    }

    public function flushCache(): int
    {
        return $this->cache->flush();
    }

    /*
    |--------------------------------------------------------------------------
    | Blade
    |--------------------------------------------------------------------------
    */

    /**
     * Backing call for the @translate directive. Target first, source second -
     * the 1.x directive silently swapped these.
     */
    public function blade(string $text, Language|string|null $to = null, Language|string|null $from = null, TextFormat|string $format = TextFormat::Text): string
    {
        return $this->build($from, $to, $format)->onFailUseSource()->text($text);
    }

    /**
     * Backing call for the @translateHtml directive: markup in, markup out, unescaped.
     */
    public function bladeHtml(string $text, Language|string|null $to = null, Language|string|null $from = null): string
    {
        return $this->blade($text, $to, $from, TextFormat::Html);
    }

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, string|array<string, string>|Closure>  $stubs
     */
    public function fake(array $stubs = []): FakeTranslator
    {
        $fake = new FakeTranslator($stubs);

        $this->container->instance(Translator::class, $fake);

        return $fake;
    }

    public function translator(): Translator
    {
        return $this->runner->translator();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Language|string|iterable<int, Language|string>|null  $to
     */
    protected function build(
        Language|string|null $from = null,
        Language|string|iterable|null $to = null,
        TextFormat|string|null $format = null,
    ): PendingTranslation {
        $pending = $this->query();

        if ($from !== null) {
            $pending = $pending->from($from);
        }

        if ($to !== null) {
            $pending = $pending->to($to);
        }

        if ($format !== null) {
            $pending = $pending->format($format);
        }

        return $pending;
    }
}
