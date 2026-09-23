<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;

final readonly class TranslationOptions
{
    /**
     * @param  array<int, string>  $targets
     * @param  array<int, string>  $patterns
     */
    public function __construct(
        public ?string $source = null,
        public array $targets = [],
        public TextFormat $format = TextFormat::Text,
        public bool $cache = true,
        public ?int $cacheTtl = null,
        public ?bool $fallbackToSource = null,
        public bool $preserve = true,
        public array $patterns = [],
        public bool $glossary = true,
        public bool $deferred = false,
    ) {}

    public function target(): string
    {
        return $this->targets[0] ?? '';
    }

    public function isMultiTarget(): bool
    {
        return count($this->targets) > 1;
    }

    /** @param array<string, mixed> $overrides */
    public function with(array $overrides): self
    {
        return new self(
            source: array_key_exists('source', $overrides) ? $overrides['source'] : $this->source,
            targets: $overrides['targets'] ?? $this->targets,
            format: $overrides['format'] ?? $this->format,
            cache: $overrides['cache'] ?? $this->cache,
            cacheTtl: array_key_exists('cacheTtl', $overrides) ? $overrides['cacheTtl'] : $this->cacheTtl,
            fallbackToSource: array_key_exists('fallbackToSource', $overrides) ? $overrides['fallbackToSource'] : $this->fallbackToSource,
            preserve: $overrides['preserve'] ?? $this->preserve,
            patterns: $overrides['patterns'] ?? $this->patterns,
            glossary: $overrides['glossary'] ?? $this->glossary,
            deferred: $overrides['deferred'] ?? $this->deferred,
        );
    }
}
