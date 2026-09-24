<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use ArrayAccess;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Stringable;

/**
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class DetectedLanguage implements Arrayable, ArrayAccess, Jsonable, JsonSerializable, Stringable
{
    /**
     * Create a new detected language instance.
     */
    public function __construct(
        public string $text,
        public string $languageCode,
        public float $confidence = 0.0,
        public bool $reliable = false,
    ) {}

    /**
     * Get the language enum for the detected code, if it is known.
     */
    public function language(): ?Language
    {
        return Language::tryFromCode($this->languageCode);
    }

    /**
     * Determine if the detected language matches the given one.
     */
    public function is(Language|string $language): bool
    {
        return Language::normalize($language) === Language::normalize($this->languageCode);
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'language_code' => $this->languageCode,
            'confidence' => $this->confidence,
            'reliable' => $this->reliable,
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
        throw new \LogicException('DetectedLanguage objects are immutable.');
    }

    /**
     * Unset the value at the given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('DetectedLanguage objects are immutable.');
    }

    /**
     * Get the detected language code.
     */
    public function __toString(): string
    {
        return $this->languageCode;
    }
}
