<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\Estimate;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\RoundTripResult;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\GoogleTranslateException;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Config;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Similarity;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationOptions;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationRunner;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Usage;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

/**
 * An immutable builder for a translation. Every modifier returns a new
 * instance, so a configured builder may be shared safely.
 */
final class PendingTranslation
{
    use Conditionable;
    use Macroable;

    /**
     * Create a new pending translation instance.
     */
    public function __construct(
        private readonly TranslationRunner $runner,
        private readonly Config $config,
        private readonly Usage $usage,
        private TranslationOptions $options,
    ) {}

    /**
     * Get the options configured so far.
     */
    public function options(): TranslationOptions
    {
        return $this->options;
    }

    /**
     * Set the source language.
     */
    public function from(Language|string|null $language): self
    {
        return $this->tap(['source' => $language === null ? null : Language::normalize($language)]);
    }

    /**
     * Let Google detect the source language, which is the default.
     */
    public function detectSource(): self
    {
        return $this->tap(['source' => null]);
    }

    /**
     * Set the target languages.
     *
     * @param  Language|string|iterable<int, Language|string>  $language
     */
    public function to(Language|string|iterable $language): self
    {
        $targets = collect(is_iterable($language) ? $language : [$language])
            ->map(fn (Language|string $target) => Language::normalize($target))
            ->unique()
            ->values()
            ->all();

        return $this->tap(['targets' => $targets]);
    }

    /**
     * Set the text format.
     */
    public function format(TextFormat|string $format): self
    {
        return $this->tap(['format' => TextFormat::make($format)]);
    }

    /**
     * Translate as HTML, leaving the markup intact.
     */
    public function asHtml(): self
    {
        return $this->format(TextFormat::Html);
    }

    /**
     * Translate as plain text.
     */
    public function asText(): self
    {
        return $this->format(TextFormat::Text);
    }

    /**
     * Cache the translations, optionally for the given number of seconds.
     */
    public function withCache(?int $ttl = null): self
    {
        return $this->tap(['cache' => true, 'cacheTtl' => $ttl]);
    }

    /**
     * Bypass the translation cache.
     */
    public function withoutCache(): self
    {
        return $this->tap(['cache' => false]);
    }

    /**
     * Return the untouched source text instead of throwing when the API fails.
     */
    public function onFailUseSource(bool $fallback = true): self
    {
        return $this->tap(['fallbackToSource' => $fallback]);
    }

    /**
     * Throw when the API fails.
     */
    public function onFailThrow(): self
    {
        return $this->tap(['fallbackToSource' => false]);
    }

    /**
     * Shield the given patterns from translation, alongside the built-in ones.
     *
     * @param  array<int, string>  $patterns  extra regexes or literal terms to shield
     */
    public function preserving(array $patterns = []): self
    {
        return $this->tap(['preserve' => true, 'patterns' => [...$this->options->patterns, ...$patterns]]);
    }

    /**
     * Send the text to the API exactly as it was given.
     */
    public function withoutPreserving(): self
    {
        return $this->tap(['preserve' => false]);
    }

    /**
     * Translate without applying the configured glossary.
     */
    public function withoutGlossary(): self
    {
        return $this->tap(['glossary' => false]);
    }

    /**
     * Run the translation after the response has been sent to the browser.
     */
    public function deferred(bool $deferred = true): self
    {
        return $this->tap(['deferred' => $deferred]);
    }

    /**
     * Translate the given text, optionally passing the result through a callback.
     *
     * @param  string|iterable<array-key, string>  $text
     * @param  (Closure(mixed): mixed)|null  $then
     * @return ($then is null ? Translation|TranslationCollection|Collection<string, Translation|TranslationCollection> : null)
     */
    public function translate(string|iterable $text, ?Closure $then = null): Translation|TranslationCollection|Collection|null
    {
        $run = fn () => $this->perform($text);

        if ($this->options->deferred) {
            $this->defer(fn () => $then === null ? $run() : $then($run()));

            return null;
        }

        $result = $run();

        return $then === null ? $result : $then($result);
    }

    /**
     * Translate the given text and return the translated string only.
     */
    public function text(string $text): string
    {
        $result = $this->perform($text);

        return $result instanceof Translation
            ? $result->translatedText
            : (string) $result->first();
    }

    /**
     * Translate the given texts in a single batch.
     *
     * @param  iterable<array-key, string>  $texts
     * @return TranslationCollection|Collection<string, TranslationCollection>
     */
    public function many(iterable $texts): TranslationCollection|Collection
    {
        $result = $this->perform($texts);

        return $result instanceof Translation ? TranslationCollection::make([$result]) : $result;
    }

    /**
     * Translate the given texts lazily, one chunk at a time, into a single target.
     *
     * @param  iterable<array-key, string>  $texts
     * @return LazyCollection<int, Translation>
     */
    public function lazy(iterable $texts, ?int $chunkSize = null): LazyCollection
    {
        if ($this->targets()->count() > 1) {
            throw new GoogleTranslateException('lazy() supports a single target language. Call to() with one language.');
        }

        $chunkSize ??= $this->config->maxSegments();

        return LazyCollection::make(function () use ($texts, $chunkSize): iterable {
            foreach (LazyCollection::make($texts)->chunk($chunkSize) as $chunk) {
                /** @var TranslationCollection $translations */
                $translations = $this->many($chunk->values()->all());

                foreach ($translations as $translation) {
                    yield $translation;
                }
            }
        });
    }

    /**
     * Translate the selected leaves of a nested array or JSON string, keeping its structure.
     *
     * @param  array<array-key, mixed>|string  $payload
     * @param  array<int, string>  $only  dot paths, wildcards allowed ("data.*.title")
     * @param  array<int, string>  $except
     * @return array<array-key, mixed>
     */
    public function json(array|string $payload, array $only = ['*'], array $except = []): array
    {
        $decoded = is_string($payload) ? (array) json_decode($payload, true, flags: JSON_THROW_ON_ERROR) : $payload;

        $leaves = collect(Arr::dot($decoded))
            ->filter(fn (mixed $value, string $path) => is_string($value) && trim($value) !== '')
            ->filter(fn (mixed $value, string $path) => Str::is($only, $path))
            ->reject(fn (mixed $value, string $path) => $except !== [] && Str::is($except, $path));

        if ($leaves->isEmpty()) {
            return $decoded;
        }

        /** @var TranslationCollection $translations */
        $translations = $this->many($leaves->values()->all());

        $translated = $translations->values();

        foreach ($leaves->keys()->values() as $position => $path) {
            $value = $translated->get($position);

            if ($value instanceof Translation) {
                Arr::set($decoded, $path, $value->translatedText);
            }
        }

        return $decoded;
    }

    /**
     * Detect the language of the given text.
     *
     * @param  string|iterable<array-key, string>  $text
     * @return DetectedLanguage|Collection<array-key, DetectedLanguage>
     */
    public function detect(string|iterable $text): DetectedLanguage|Collection
    {
        $single = is_string($text);
        $texts = $single ? [$text] : collect($text)->map(fn (mixed $value): string => (string) $value)->values()->all();

        $detections = $this->runner->detect($texts);

        return $single ? $detections[0] : collect($detections);
    }

    /**
     * Translate the text out to a pivot language and back, then score what survived.
     */
    public function roundTrip(string $text, Language|string|null $via = null): RoundTripResult
    {
        $source = $this->options->source ?? $this->detect($text)->languageCode;
        $pivot = Language::normalize($via ?? $this->targets()->first() ?? $this->config->defaultTarget());

        $forward = $this->from($source)->to($pivot)->withoutCache()->text($text);
        $back = $this->from($pivot)->to($source)->withoutCache()->text($forward);

        return new RoundTripResult(
            source: $text,
            translated: $forward,
            back: $back,
            sourceLanguage: $source,
            pivotLanguage: $pivot,
            score: Similarity::score($text, $back),
        );
    }

    /**
     * Estimate what translating the given texts would cost.
     *
     * @param  string|iterable<array-key, string>  $texts
     */
    public function estimate(string|iterable $texts): Estimate
    {
        $segments = is_string($texts) ? [$texts] : collect($texts)->map(fn (mixed $value): string => (string) $value)->values()->all();
        $targets = $this->targets()->all();

        $cached = 0;

        if ($this->options->cache) {
            foreach ($targets as $target) {
                $cached += count($segments) - count($this->missesFor($segments, $target));
            }
        }

        return $this->usage->estimate($segments, $targets, $cached);
    }

    /**
     * Get the target languages.
     *
     * @return Collection<int, string>
     */
    public function targets(): Collection
    {
        return collect($this->options->targets);
    }

    /**
     * Run the translation and shape the results for the caller.
     *
     * @param  string|iterable<array-key, string>  $text
     * @return Translation|TranslationCollection|Collection<string, Translation|TranslationCollection>
     */
    private function perform(string|iterable $text): Translation|TranslationCollection|Collection
    {
        $single = is_string($text);
        $texts = $single ? [$text] : collect($text)->map(fn (mixed $value): string => (string) $value)->values()->all();

        $results = $this->runner->run($texts, $this->options);

        $byTarget = collect($results)->map(
            fn (array $translations, string $target) => $single
                ? (Arr::first($translations) ?? $this->unchanged($texts[0] ?? '', $target))
                : TranslationCollection::make(array_values($translations))
        );

        if ($this->options->isMultiTarget()) {
            return $byTarget;
        }

        return $byTarget->first() ?? TranslationCollection::make();
    }

    /**
     * Get the segments of the given batch that are not cached yet.
     *
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function missesFor(array $texts, string $target): array
    {
        return $this->runner->cacheMisses($texts, $this->options->source, $target, $this->options->format);
    }

    /**
     * Build the translation used when the driver returns nothing for a segment.
     */
    private function unchanged(string $text, string $target): Translation
    {
        return new Translation(
            sourceText: $text,
            translatedText: $text,
            sourceLanguage: $this->options->source,
            targetLanguage: $target,
            format: $this->options->format,
        );
    }

    /**
     * Run the given callback once the response has been sent, when that is possible.
     */
    private function defer(Closure $callback): void
    {
        if (function_exists('defer') && ! app()->runningInConsole()) {
            defer($callback);

            return;
        }

        $callback();
    }

    /**
     * Create a copy of the builder with the given options replaced.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function tap(array $overrides): self
    {
        $clone = new self($this->runner, $this->config, $this->usage, $this->options->with($overrides));

        return $clone;
    }
}
