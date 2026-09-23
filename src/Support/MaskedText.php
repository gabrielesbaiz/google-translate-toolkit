<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

/**
 * A string whose placeholders have been swapped for sentinels.
 */
final readonly class MaskedText
{
    /** @param array<string, string> $map sentinel => original token */
    public function __construct(public string $text, public array $map = []) {}

    public function isMasked(): bool
    {
        return $this->map !== [];
    }
}
