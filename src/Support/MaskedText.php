<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

/**
 * A string whose placeholders have been swapped for sentinels.
 */
final readonly class MaskedText
{
    /**
     * Create a new masked text instance.
     *
     * @param  array<string, string>  $map  the sentinels keyed to the tokens they replaced
     */
    public function __construct(public string $text, public array $map = []) {}

    /**
     * Determine if anything was masked.
     */
    public function isMasked(): bool
    {
        return $this->map !== [];
    }
}
