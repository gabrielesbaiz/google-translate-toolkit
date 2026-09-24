<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class Estimate implements Arrayable, JsonSerializable
{
    /**
     * Create a new estimate instance.
     */
    public function __construct(
        public int $segments,
        public int $characters,
        public int $billableCharacters,
        public int $requests,
        public int $targets,
        public float $cost,
        public string $currency = 'USD',
        public int $cachedSegments = 0,
    ) {}

    /**
     * Get the estimated cost formatted with its currency.
     */
    public function formattedCost(): string
    {
        return sprintf('%s %s', $this->currency, number_format($this->cost, 4));
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'segments' => $this->segments,
            'characters' => $this->characters,
            'billable_characters' => $this->billableCharacters,
            'cached_segments' => $this->cachedSegments,
            'requests' => $this->requests,
            'targets' => $this->targets,
            'cost' => round($this->cost, 6),
            'currency' => $this->currency,
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
