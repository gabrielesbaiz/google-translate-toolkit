<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Drivers;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * A deterministic in-memory translator that records every call made against it.
 */
final class FakeTranslator implements Translator
{
    /**
     * The translation calls that have been recorded.
     *
     * @var array<int, array{texts: array<int, string>, source: ?string, target: string, format: string}>
     */
    private array $translations = [];

    /**
     * The detection calls that have been recorded.
     *
     * @var array<int, array<int, string>>
     */
    private array $detections = [];

    /**
     * Create a new fake translator instance.
     *
     * @param  array<string, string|array<string, string>|Closure>  $stubs
     */
    public function __construct(
        private array $stubs = [],
        private string $detectedLanguage = 'en',
    ) {}

    /**
     * Register additional stubbed translations.
     *
     * @param  array<string, string|array<string, string>|Closure>  $stubs
     */
    public function stub(array $stubs): self
    {
        $this->stubs = [...$this->stubs, ...$stubs];

        return $this;
    }

    /**
     * Set the language every detection should report.
     */
    public function detectAs(string $language): self
    {
        $this->detectedLanguage = $language;

        return $this;
    }

    /**
     * Translate the given segments into the target language.
     */
    public function translate(array $texts, ?string $source, string $target, TextFormat $format): array
    {
        return $this->translateMany($texts, $source, [$target], $format)[$target] ?? [];
    }

    /**
     * Translate the given segments into several target languages at once.
     */
    public function translateMany(array $texts, ?string $source, array $targets, TextFormat $format): array
    {
        $results = [];

        foreach (array_unique($targets) as $target) {
            $this->translations[] = [
                'texts' => array_values($texts),
                'source' => $source,
                'target' => $target,
                'format' => $format->value,
            ];

            foreach ($texts as $key => $text) {
                $results[$target][$key] = [
                    'text' => $this->resolve($text, $target),
                    'detectedSourceLanguage' => $source === null ? $this->detectedLanguage : null,
                ];
            }
        }

        return $results;
    }

    /**
     * Detect the language of the given segments.
     */
    public function detect(array $texts): array
    {
        $this->detections[] = array_values($texts);

        return array_map(fn () => [
            'language' => $this->detectedLanguage,
            'confidence' => 1.0,
            'reliable' => true,
        ], $texts);
    }

    /**
     * Get the languages the driver can translate, named in the given display language.
     */
    public function languages(string $displayLanguage): array
    {
        return [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => $displayLanguage, 'name' => $displayLanguage],
        ];
    }

    /**
     * Get the translation calls that have been recorded.
     *
     * @return Collection<int, array{texts: array<int, string>, source: ?string, target: string, format: string}>
     */
    public function recorded(): Collection
    {
        return collect($this->translations);
    }

    /**
     * Get the detection calls that have been recorded.
     *
     * @return Collection<int, array<int, string>>
     */
    public function recordedDetections(): Collection
    {
        return collect($this->detections);
    }

    /**
     * Get every segment that has been sent for translation.
     *
     * @return Collection<int, string>
     */
    public function translatedTexts(): Collection
    {
        return $this->recorded()->flatMap(fn (array $call) => $call['texts'])->values();
    }

    /**
     * Assert the given text was translated.
     */
    public function assertTranslated(string|Closure $text, ?string $target = null): self
    {
        $matches = $this->recorded()->filter(function (array $call) use ($text, $target): bool {
            if ($target !== null && $call['target'] !== $target) {
                return false;
            }

            return $text instanceof Closure
                ? (bool) $text($call['texts'], $call['target'], $call['source'])
                : in_array($text, $call['texts'], true);
        });

        PHPUnit::assertTrue(
            $matches->isNotEmpty(),
            'The expected text was not translated. Recorded: '.json_encode($this->translatedTexts()->all(), JSON_UNESCAPED_UNICODE),
        );

        return $this;
    }

    /**
     * Assert the given text was never translated.
     */
    public function assertNotTranslated(string $text): self
    {
        PHPUnit::assertFalse(
            $this->translatedTexts()->contains($text),
            sprintf('The text [%s] was unexpectedly translated.', $text),
        );

        return $this;
    }

    /**
     * Assert no translation was performed.
     */
    public function assertNothingTranslated(): self
    {
        PHPUnit::assertEmpty($this->translations, 'Unexpected translations were performed.');

        return $this;
    }

    /**
     * Assert the given number of translation calls were made.
     */
    public function assertTranslatedCount(int $count): self
    {
        PHPUnit::assertCount($count, $this->translations, 'Unexpected number of translation calls.');

        return $this;
    }

    /**
     * Assert something was translated into the given language.
     */
    public function assertTranslatedTo(string $target): self
    {
        PHPUnit::assertTrue(
            $this->recorded()->contains(fn (array $call) => $call['target'] === $target),
            sprintf('Nothing was translated to [%s].', $target),
        );

        return $this;
    }

    /**
     * Assert a language detection was performed, optionally for the given text.
     */
    public function assertDetected(?string $text = null): self
    {
        PHPUnit::assertNotEmpty($this->detections, 'No language detection was performed.');

        if ($text !== null) {
            PHPUnit::assertTrue(
                $this->recordedDetections()->contains(fn (array $texts) => in_array($text, $texts, true)),
                sprintf('The text [%s] was never sent for detection.', $text),
            );
        }

        return $this;
    }

    /**
     * Resolve the stubbed translation for the given segment.
     */
    private function resolve(string $text, string $target): string
    {
        $stub = $this->stubs[$text] ?? null;

        if ($stub instanceof Closure) {
            return (string) $stub($text, $target);
        }

        if (is_array($stub)) {
            return (string) ($stub[$target] ?? $this->placeholder($text, $target));
        }

        return is_string($stub) ? $stub : $this->placeholder($text, $target);
    }

    /**
     * Build the stand-in translation used when no stub matches.
     */
    private function placeholder(string $text, string $target): string
    {
        return sprintf('[%s] %s', $target, $text);
    }
}
