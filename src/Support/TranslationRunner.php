<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\DetectedLanguage;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Gabrielesbaiz\GoogleTranslateToolkit\Events\TranslationCompleted;
use Gabrielesbaiz\GoogleTranslateToolkit\Events\TranslationFailed;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\GoogleTranslateException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Where a translation actually happens: masking, cache, budget, driver, restore.
 */
final class TranslationRunner
{
    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly TranslationCache $cache,
        private readonly Placeholders $placeholders,
        private readonly Glossary $glossary,
        private readonly Usage $usage,
        private readonly Dispatcher $events,
    ) {}

    public function translator(): Translator
    {
        return $this->container->make(Translator::class);
    }

    /**
     * @param  array<array-key, string>  $texts
     * @return array<string, array<array-key, Translation>>
     */
    public function run(array $texts, TranslationOptions $options): array
    {
        $source = $this->code($options->source);
        $targets = array_values(array_unique(array_map(fn (string $target) => (string) $this->code($target), $options->targets)));

        if ($texts === [] || $targets === []) {
            return array_fill_keys($targets, []);
        }

        $masked = $this->mask($texts, $options);

        $plans = [];

        foreach ($targets as $target) {
            $partition = $options->cache
                ? $this->cache->partition($texts, $source, $target, $options->format)
                : ['hits' => [], 'misses' => $texts];

            $plans[$target] = $partition;
        }

        $results = [];

        foreach ($this->groupByMisses($plans) as $group) {
            $misses = $group['misses'];
            $groupTargets = $group['targets'];

            $translated = $misses === []
                ? array_fill_keys($groupTargets, [])
                : $this->send($misses, $masked, $source, $groupTargets, $options);

            foreach ($groupTargets as $target) {
                $results[$target] = $this->assemble(
                    $texts,
                    $masked,
                    $plans[$target]['hits'],
                    $translated[$target] ?? [],
                    $source,
                    $target,
                    $options,
                );
            }
        }

        return $results;
    }

    /**
     * @param  array<array-key, string>  $texts
     * @return array<array-key, DetectedLanguage>
     */
    public function detect(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $this->usage->guardBudget(array_sum(array_map(mb_strlen(...), $texts)));

        $detections = $this->translator()->detect($texts);

        $this->usage->record(array_sum(array_map(mb_strlen(...), $texts)), calls: 1);

        $results = [];

        foreach ($texts as $key => $text) {
            $detection = $detections[$key] ?? ['language' => 'und', 'confidence' => 0.0, 'reliable' => false];

            $results[$key] = new DetectedLanguage(
                text: $text,
                languageCode: (string) $detection['language'],
                confidence: (float) $detection['confidence'],
                reliable: (bool) $detection['reliable'],
            );
        }

        return $results;
    }

    /**
     * @return Collection<string, string>
     */
    public function languages(?string $displayLanguage = null): Collection
    {
        $display = (string) $this->code($displayLanguage ?? $this->config->defaultTarget());

        $languages = $this->cache->remember(
            'languages:'.$display,
            fn (): array => $this->translator()->languages($display),
            86400,
        );

        return collect($languages)->mapWithKeys(fn (array $language) => [$language['code'] => $language['name']]);
    }

    /**
     * Which segments of this batch are not cached yet.
     *
     * @param  array<array-key, string>  $texts
     * @return array<array-key, string>
     */
    public function cacheMisses(array $texts, ?string $source, string $target, TextFormat $format): array
    {
        return $this->cache->partition($texts, $this->code($source), (string) $this->code($target), $format)['misses'];
    }

    public function normalizeCode(?string $code): ?string
    {
        return $this->code($code);
    }

    /**
     * @param  array<array-key, string>  $texts
     * @return array<array-key, MaskedText>
     */
    private function mask(array $texts, TranslationOptions $options): array
    {
        if (! $options->preserve) {
            return array_map(fn (string $text) => new MaskedText($text), $texts);
        }

        $patterns = [...$options->patterns];

        if ($options->glossary) {
            $patterns = [...$this->glossary->protectedPatterns(), ...$patterns];
        }

        return $this->placeholders->withPatterns($patterns)->maskAll($texts, $options->format);
    }

    /**
     * Targets sharing the same set of cache misses travel in one pooled request set.
     *
     * @param  array<string, array{hits: array<array-key, string>, misses: array<array-key, string>}>  $plans
     * @return array<int, array{targets: array<int, string>, misses: array<array-key, string>}>
     */
    private function groupByMisses(array $plans): array
    {
        $groups = [];

        foreach ($plans as $target => $plan) {
            $signature = md5(implode('|', array_map(strval(...), array_keys($plan['misses']))));

            $groups[$signature]['misses'] = $plan['misses'];
            $groups[$signature]['targets'][] = (string) $target;
        }

        return array_values($groups);
    }

    /**
     * @param  array<array-key, string>  $misses
     * @param  array<array-key, MaskedText>  $masked
     * @param  array<int, string>  $targets
     * @return array<string, array<array-key, array{text: string, detectedSourceLanguage: string|null}>>
     */
    private function send(array $misses, array $masked, ?string $source, array $targets, TranslationOptions $options): array
    {
        $payload = [];

        foreach (array_keys($misses) as $key) {
            $payload[$key] = $masked[$key]->text ?? $misses[$key];
        }

        $characters = array_sum(array_map(mb_strlen(...), $payload)) * count($targets);

        $this->usage->guardBudget($characters);

        try {
            $translated = $this->translator()->translateMany($payload, $source, $targets, $options->format);
        } catch (Throwable $exception) {
            return $this->recover($exception, $payload, $misses, $source, $targets, $options);
        }

        $this->usage->record($characters, calls: count($targets));

        return $translated;
    }

    /**
     * @param  array<array-key, string>  $payload
     * @param  array<array-key, string>  $misses
     * @param  array<int, string>  $targets
     * @return array<string, array<array-key, array{text: string, detectedSourceLanguage: string|null}>>
     */
    private function recover(Throwable $exception, array $payload, array $misses, ?string $source, array $targets, TranslationOptions $options): array
    {
        $fallback = $options->fallbackToSource ?? $this->config->fallbackToSource();

        $this->usage->record(0, failures: 1);

        foreach ($targets as $target) {
            $this->fire(new TranslationFailed($exception, array_values($misses), $target, $source, $fallback));
        }

        if (! $fallback) {
            throw $exception instanceof GoogleTranslateException
                ? $exception
                : new GoogleTranslateException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        // Degrade gracefully: hand back the untouched source text.
        $results = [];

        foreach ($targets as $target) {
            foreach ($payload as $key => $text) {
                $results[$target][$key] = ['text' => $misses[$key], 'detectedSourceLanguage' => null];
            }
        }

        return $results;
    }

    /**
     * @param  array<array-key, string>  $texts
     * @param  array<array-key, MaskedText>  $masked
     * @param  array<array-key, string>  $hits
     * @param  array<array-key, array{text: string, detectedSourceLanguage: string|null}>  $translated
     * @return array<array-key, Translation>
     */
    private function assemble(array $texts, array $masked, array $hits, array $translated, ?string $source, string $target, TranslationOptions $options): array
    {
        $translations = [];
        $characters = 0;

        foreach ($texts as $key => $text) {
            if (array_key_exists($key, $hits)) {
                $translations[$key] = new Translation(
                    sourceText: $text,
                    translatedText: $hits[$key],
                    sourceLanguage: $source,
                    targetLanguage: $target,
                    format: $options->format,
                    detected: $source === null,
                    cached: true,
                );

                continue;
            }

            $result = $translated[$key] ?? ['text' => $text, 'detectedSourceLanguage' => null];

            $final = $this->placeholders->restore((string) $result['text'], $masked[$key]->map ?? []);

            if ($options->glossary) {
                $final = $this->glossary->apply($final, $target);
            }

            if ($options->cache) {
                $this->cache->put($text, $final, $source, $target, $options->format, $options->cacheTtl);
            }

            $characters += mb_strlen($text);

            $translations[$key] = new Translation(
                sourceText: $text,
                translatedText: $final,
                sourceLanguage: $result['detectedSourceLanguage'] ?? $source,
                targetLanguage: $target,
                format: $options->format,
                detected: $result['detectedSourceLanguage'] !== null,
                cached: false,
            );
        }

        $this->usage->record(0, cacheHits: count($hits));

        $this->fire(new TranslationCompleted(
            translations: array_values($translations),
            target: $target,
            source: $source,
            characters: $characters,
            cacheHits: count($hits),
            requests: $translated === [] ? 0 : 1,
        ));

        return $translations;
    }

    private function fire(object $event): void
    {
        if ($this->config->eventsEnabled()) {
            $this->events->dispatch($event);
        }
    }

    private function code(?string $code): ?string
    {
        if (blank($code)) {
            return null;
        }

        return $this->config->strictLanguages()
            ? Language::fromCode($code)->value
            : Language::normalize($code);
    }
}
