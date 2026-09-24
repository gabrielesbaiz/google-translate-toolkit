<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The result of translating a string out to a pivot language and back again.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class RoundTripResult implements Arrayable, JsonSerializable
{
    /**
     * Create a new round trip result instance.
     */
    public function __construct(
        public string $source,
        public string $translated,
        public string $back,
        public string $sourceLanguage,
        public string $pivotLanguage,
        public float $score,
    ) {}

    /**
     * Determine if too little meaning survived the round trip.
     */
    public function isSuspicious(float $threshold = 0.6): bool
    {
        return $this->score < $threshold;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'translated' => $this->translated,
            'back' => $this->back,
            'source_language' => $this->sourceLanguage,
            'pivot_language' => $this->pivotLanguage,
            'score' => round($this->score, 4),
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
}
