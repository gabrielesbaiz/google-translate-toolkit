<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use ArrayAccess;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stringable;

/**
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Translation implements Arrayable, ArrayAccess, Jsonable, JsonSerializable, Stringable
{
    public function __construct(
        public string $sourceText,
        public string $translatedText,
        public ?string $sourceLanguage,
        public string $targetLanguage,
        public TextFormat $format = TextFormat::Text,
        public bool $detected = false,
        public bool $cached = false,
    ) {}

    public function sourceLanguage(): ?Language
    {
        return Language::tryFromCode($this->sourceLanguage);
    }

    public function targetLanguage(): ?Language
    {
        return Language::tryFromCode($this->targetLanguage);
    }

    public function isUnchanged(): bool
    {
        return $this->sourceText === $this->translatedText;
    }

    public function cached(bool $cached = true): self
    {
        return new self(
            $this->sourceText,
            $this->translatedText,
            $this->sourceLanguage,
            $this->targetLanguage,
            $this->format,
            $this->detected,
            $cached,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_text' => $this->sourceText,
            'source_language_code' => $this->sourceLanguage,
            'translated_text' => $this->translatedText,
            'translated_language_code' => $this->targetLanguage,
            'format' => $this->format->value,
            'detected' => $this->detected,
            'cached' => $this->cached,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Translation objects are immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Translation objects are immutable.');
    }

    public function __toString(): string
    {
        return $this->translatedText;
    }
}
