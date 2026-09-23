<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Back-translation QA: how much meaning survived the trip out and back.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class RoundTripResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $source,
        public string $translated,
        public string $back,
        public string $sourceLanguage,
        public string $pivotLanguage,
        public float $score,
    ) {}

    public function isSuspicious(float $threshold = 0.6): bool
    {
        return $this->score < $threshold;
    }

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
