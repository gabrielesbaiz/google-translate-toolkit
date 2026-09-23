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
    public function __construct(
        public string $text,
        public string $languageCode,
        public float $confidence = 0.0,
        public bool $reliable = false,
    ) {}

    public function language(): ?Language
    {
        return Language::tryFromCode($this->languageCode);
    }

    public function is(Language|string $language): bool
    {
        return Language::normalize($language) === Language::normalize($this->languageCode);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'language_code' => $this->languageCode,
            'confidence' => $this->confidence,
            'reliable' => $this->reliable,
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
        throw new \LogicException('DetectedLanguage objects are immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('DetectedLanguage objects are immutable.');
    }

    public function __toString(): string
    {
        return $this->languageCode;
    }
}
