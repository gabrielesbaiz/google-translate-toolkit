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
    /**
     * Create a new translation instance.
     */
    public function __construct(
        public string $sourceText,
        public string $translatedText,
        public ?string $sourceLanguage,
        public string $targetLanguage,
        public TextFormat $format = TextFormat::Text,
        public bool $detected = false,
        public bool $cached = false,
    ) {}

    /**
     * Get the language enum for the source code, if it is known.
     */
    public function sourceLanguage(): ?Language
    {
        return Language::tryFromCode($this->sourceLanguage);
    }

    /**
     * Get the language enum for the target code, if it is known.
     */
    public function targetLanguage(): ?Language
    {
        return Language::tryFromCode($this->targetLanguage);
    }

    /**
     * Determine if the translation is identical to the source text.
     */
    public function isUnchanged(): bool
    {
        return $this->sourceText === $this->translatedText;
    }

    /**
     * Create a copy of the translation flagged as served from the cache.
     */
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

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Determine if the given offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    /**
     * Set the value at the given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Translation objects are immutable.');
    }

    /**
     * Unset the value at the given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Translation objects are immutable.');
    }

    /**
     * Get the translated text.
     */
    public function __toString(): string
    {
        return $this->translatedText;
    }
}
