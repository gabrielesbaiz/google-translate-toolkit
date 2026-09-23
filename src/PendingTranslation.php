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
 * Immutable fluent builder. Every modifier returns a new instance, so a
 * configured builder can be shared safely.
 */
final class PendingTranslation
{
    use Conditionable;
    use Macroable;

    public function __construct(
        private readonly TranslationRunner $runner,
        private readonly Config $config,
        private readonly Usage $usage,
        private TranslationOptions $options,
    ) {}

    public function options(): TranslationOptions
    {
        return $this->options;
    }

    public function from(Language|string|null $language): self
    {
        return $this->tap(['source' => $language === null ? null : Language::normalize($language)]);
    }

    /**
     * Auto-detect the source language (the default, and the cheapest option).
     */
    public function detectSource(): self
    {
        return $this->tap(['source' => null]);
    }

    /**
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

    public function format(TextFormat|string $format): self
    {
        return $this->tap(['format' => TextFormat::make($format)]);
    }

    public function asHtml(): self
    {
        return $this->format(TextFormat::Html);
    }

    public function asText(): self
    {
        return $this->format(TextFormat::Text);
    }

    public function withCache(?int $ttl = null): self
    {
        return $this->tap(['cache' => true, 'cacheTtl' => $ttl]);
    }

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

    public function onFailThrow(): self
    {
        return $this->tap(['fallbackToSource' => false]);
    }

    /**
     * @param  array<int, string>  $patterns  extra regexes or literal terms to shield
     */
    public function preserving(array $patterns = []): self
    {
        return $this->tap(['preserve' => true, 'patterns' => [...$this->options->patterns, ...$patterns]]);
    }

    public function withoutPreserving(): self
    {
        return $this->tap(['preserve' => false]);
    }

    public function withoutGlossary(): self
    {
        return $this->tap(['glossary' => false]);
    }

    /**
     * Run after the response has been sent to the browser.
     */
    public function deferred(bool $deferred = true): self
    {
        return $this->tap(['deferred' => $deferred]);
    }

    /**
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
     * The translated string only - the 1.x shorthand.
     */
    public function text(string $text): string
    {
        $result = $this->perform($text);

        return $result instanceof Translation
            ? $result->translatedText
            : (string) $result->first();
    }

    /**
     * @param  iterable<array-key, string>  $texts
     * @return TranslationCollection|Collection<string, TranslationCollection>
     */
    public function many(iterable $texts): TranslationCollection|Collection
    {
        $result = $this->perform($texts);

        return $result instanceof Translation ? TranslationCollection::make([$result]) : $result;
    }

    /**
     * Memory-safe streaming for very large sets. Single target only.
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
     * Deep-translate selected leaves of a nested array or JSON string, keeping the structure.
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
     * Back-translation QA: translate out, translate back, score what survived.
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
     * What this call would cost, without spending anything.
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

    /** @return Collection<int, string> */
    public function targets(): Collection
    {
        return collect($this->options->targets);
    }

    /**
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
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function missesFor(array $texts, string $target): array
    {
        return $this->runner->cacheMisses($texts, $this->options->source, $target, $this->options->format);
    }

    /**
     * Last-resort value when a driver returns nothing for a segment.
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

    private function defer(Closure $callback): void
    {
        if (function_exists('defer') && ! app()->runningInConsole()) {
            defer($callback);

            return;
        }

        $callback();
    }

    /** @param array<string, mixed> $overrides */
    private function tap(array $overrides): self
    {
        $clone = new self($this->runner, $this->config, $this->usage, $this->options->with($overrides));

        return $clone;
    }
}
