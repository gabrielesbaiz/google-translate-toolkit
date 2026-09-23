<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Drivers;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Deterministic in-memory translator for tests: no HTTP, full recording.
 */
final class FakeTranslator implements Translator
{
    /** @var array<int, array{texts: array<int, string>, source: ?string, target: string, format: string}> */
    private array $translations = [];

    /** @var array<int, array<int, string>> */
    private array $detections = [];

    /** @param array<string, string|array<string, string>|Closure> $stubs */
    public function __construct(
        private array $stubs = [],
        private string $detectedLanguage = 'en',
    ) {}

    /** @param array<string, string|array<string, string>|Closure> $stubs */
    public function stub(array $stubs): self
    {
        $this->stubs = [...$this->stubs, ...$stubs];

        return $this;
    }

    public function detectAs(string $language): self
    {
        $this->detectedLanguage = $language;

        return $this;
    }

    public function translate(array $texts, ?string $source, string $target, TextFormat $format): array
    {
        return $this->translateMany($texts, $source, [$target], $format)[$target] ?? [];
    }

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

    public function detect(array $texts): array
    {
        $this->detections[] = array_values($texts);

        return array_map(fn () => [
            'language' => $this->detectedLanguage,
            'confidence' => 1.0,
            'reliable' => true,
        ], $texts);
    }

    public function languages(string $displayLanguage): array
    {
        return [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => $displayLanguage, 'name' => $displayLanguage],
        ];
    }

    /** @return Collection<int, array{texts: array<int, string>, source: ?string, target: string, format: string}> */
    public function recorded(): Collection
    {
        return collect($this->translations);
    }

    /** @return Collection<int, array<int, string>> */
    public function recordedDetections(): Collection
    {
        return collect($this->detections);
    }

    /** @return Collection<int, string> */
    public function translatedTexts(): Collection
    {
        return $this->recorded()->flatMap(fn (array $call) => $call['texts'])->values();
    }

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

    public function assertNotTranslated(string $text): self
    {
        PHPUnit::assertFalse(
            $this->translatedTexts()->contains($text),
            sprintf('The text [%s] was unexpectedly translated.', $text),
        );

        return $this;
    }

    public function assertNothingTranslated(): self
    {
        PHPUnit::assertEmpty($this->translations, 'Unexpected translations were performed.');

        return $this;
    }

    public function assertTranslatedCount(int $count): self
    {
        PHPUnit::assertCount($count, $this->translations, 'Unexpected number of translation calls.');

        return $this;
    }

    public function assertTranslatedTo(string $target): self
    {
        PHPUnit::assertTrue(
            $this->recorded()->contains(fn (array $call) => $call['target'] === $target),
            sprintf('Nothing was translated to [%s].', $target),
        );

        return $this;
    }

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

    private function placeholder(string $text, string $target): string
    {
        return sprintf('[%s] %s', $target, $text);
    }
}
