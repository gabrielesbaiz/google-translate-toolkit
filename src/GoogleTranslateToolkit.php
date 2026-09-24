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

    /**
     * Create a new toolkit instance.
     */
    public function __construct(
        protected readonly TranslationRunner $runner,
        protected readonly Config $config,
        protected readonly Usage $usage,
        protected readonly TranslationCache $cache,
        protected readonly Container $container,
    ) {}

    /**
     * Create a new pending translation configured with the defaults.
     */
    public function query(): PendingTranslation
    {
        return new PendingTranslation($this->runner, $this->config, $this->usage, new TranslationOptions(
            source: $this->config->defaultSource(),
            targets: [$this->config->defaultTarget()],
            format: TextFormat::make($this->config->defaultFormat()),
        ));
    }

    /**
     * Set the source language of a new pending translation.
     */
    public function from(Language|string|null $language): PendingTranslation
    {
        return $this->query()->from($language);
    }

    /**
     * Set the target languages of a new pending translation.
     *
     * @param  Language|string|iterable<int, Language|string>  $language
     */
    public function to(Language|string|iterable $language): PendingTranslation
    {
        return $this->query()->to($language);
    }

    /**
     * Set the text format of a new pending translation.
     */
    public function format(TextFormat|string $format): PendingTranslation
    {
        return $this->query()->format($format);
    }

    /**
     * Translate as HTML, leaving the markup intact.
     */
    public function asHtml(): PendingTranslation
    {
        return $this->query()->asHtml();
    }

    /**
     * Translate as plain text.
     */
    public function asText(): PendingTranslation
    {
        return $this->query()->asText();
    }

    /**
     * Cache the translations, optionally for the given number of seconds.
     */
    public function withCache(?int $ttl = null): PendingTranslation
    {
        return $this->query()->withCache($ttl);
    }

    /**
     * Bypass the translation cache.
     */
    public function withoutCache(): PendingTranslation
    {
        return $this->query()->withoutCache();
    }

    /**
     * Return the untouched source text instead of throwing when the API fails.
     */
    public function onFailUseSource(bool $fallback = true): PendingTranslation
    {
        return $this->query()->onFailUseSource($fallback);
    }

    /**
     * Shield the given patterns from translation, alongside the built-in ones.
     *
     * @param  array<int, string>  $patterns
     */
    public function preserving(array $patterns = []): PendingTranslation
    {
        return $this->query()->preserving($patterns);
    }

    /**
     * Translate without applying the configured glossary.
     */
    public function withoutGlossary(): PendingTranslation
    {
        return $this->query()->withoutGlossary();
    }

    /**
     * Run the translation after the response has been sent to the browser.
     */
    public function deferred(bool $deferred = true): PendingTranslation
    {
        return $this->query()->deferred($deferred);
    }

    /**
     * Translate the given text.
     *
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
     * Translate the given text and return the translated string only.
     *
     * @param  Language|string|iterable<int, Language|string>|null  $to
     */
    public function justTranslate(string $text, Language|string|null $from = null, Language|string|iterable|null $to = null): string
    {
        return $this->build($from, $to)->text($text);
    }

    /**
     * Translate the given texts in a single batch.
     *
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
     * Translate the selected leaves of a nested array or JSON string.
     *
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
     * Translate the given texts lazily, one chunk at a time.
     *
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
     * Translate the text unless it is already in the given language.
     *
     * A translation is always returned, even when nothing was sent to the API.
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

    /**
     * Determine if the given text is already in the given language.
     */
    public function isLanguage(string $text, Language|string $language): bool
    {
        return $this->detect($text)->is($language);
    }

    /**
     * Detect the language of the given text.
     *
     * @param  string|iterable<array-key, string>  $text
     * @return DetectedLanguage|Collection<array-key, DetectedLanguage>
     */
    public function detect(string|iterable $text): DetectedLanguage|Collection
    {
        return $this->query()->detect($text);
    }

    /**
     * Detect the language of the given input.
     *
     * @param  string|iterable<array-key, string>  $input
     * @return DetectedLanguage|Collection<array-key, DetectedLanguage>
     */
    public function detectLanguage(string|iterable $input): DetectedLanguage|Collection
    {
        return $this->detect($input);
    }

    /**
     * Detect the language of each of the given inputs.
     *
     * @param  iterable<array-key, string>  $input
     * @return Collection<array-key, DetectedLanguage>
     */
    public function detectLanguageBatch(iterable $input): Collection
    {
        /** @var Collection<array-key, DetectedLanguage> $detections */
        $detections = $this->detect(collect($input)->values()->all());

        return $detections;
    }

    /**
     * Get the languages Google can translate, named in the given display language.
     *
     * @return Collection<string, string>
     */
    public function languages(Language|string|null $displayLanguage = null): Collection
    {
        return $this->runner->languages($displayLanguage === null ? null : Language::normalize($displayLanguage));
    }

    /**
     * Get the language list bundled with the package, without calling the API.
     *
     * @return Collection<string, string>
     */
    public function supportedLanguages(): Collection
    {
        return Language::options();
    }

    /**
     * Get the languages Google can translate, named in the given language.
     *
     * @return Collection<string, string>
     */
    public function getAvailableTranslationsFor(Language|string $languageCode): Collection
    {
        return $this->languages($languageCode);
    }

    /**
     * Normalize the given language code, validating it when strict mode is on.
     */
    public function sanitizeLanguageCode(Language|string $languageCode): string
    {
        return $this->config->strictLanguages()
            ? Language::fromCode($languageCode)->value
            : Language::normalize($languageCode);
    }

    /**
     * Translate the text out to a pivot language and back, then score what survived.
     */
    public function roundTrip(string $text, Language|string|null $via = null, Language|string|null $from = null): RoundTripResult
    {
        return $this->build($from, $via)->roundTrip($text, $via);
    }

    /**
     * Estimate what translating the given texts would cost.
     *
     * @param  string|iterable<array-key, string>  $texts
     * @param  array<int, string>  $targets
     */
    public function estimate(string|iterable $texts, array $targets = []): Estimate
    {
        return $this->build(null, $targets === [] ? null : $targets)->estimate($texts);
    }

    /**
     * Get the usage recorder.
     */
    public function usage(): Usage
    {
        return $this->usage;
    }

    /**
     * Get the usage recorded over the last given number of days.
     *
     * @return Collection<string, array<string, int|float>>
     */
    public function stats(int $days = 7): Collection
    {
        return $this->usage->forDays($days);
    }

    /**
     * Queue a batch of jobs that pre-translate the given texts into the cache.
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

    /**
     * Flush every translation this package cached.
     */
    public function flushCache(): int
    {
        return $this->cache->flush();
    }

    /**
     * Translate the text for the @translate directive, falling back to the source.
     *
     * The target language comes first here, the source second.
     */
    public function blade(string $text, Language|string|null $to = null, Language|string|null $from = null, TextFormat|string $format = TextFormat::Text): string
    {
        return $this->build($from, $to, $format)->onFailUseSource()->text($text);
    }

    /**
     * Translate the markup for the @translateHtml directive, leaving it unescaped.
     */
    public function bladeHtml(string $text, Language|string|null $to = null, Language|string|null $from = null): string
    {
        return $this->blade($text, $to, $from, TextFormat::Html);
    }

    /**
     * Replace the translator driver with a fake for the rest of the test.
     *
     * @param  array<string, string|array<string, string>|Closure>  $stubs
     */
    public function fake(array $stubs = []): FakeTranslator
    {
        $fake = new FakeTranslator($stubs);

        $this->container->instance(Translator::class, $fake);

        return $fake;
    }

    /**
     * Resolve the translator driver.
     */
    public function translator(): Translator
    {
        return $this->runner->translator();
    }

    /**
     * Build a pending translation from the given overrides.
     *
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
