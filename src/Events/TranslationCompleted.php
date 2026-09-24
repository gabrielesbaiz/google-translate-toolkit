<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Events;

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Illuminate\Foundation\Events\Dispatchable;

class TranslationCompleted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<int, Translation>  $translations
     */
    public function __construct(
        public readonly array $translations,
        public readonly string $target,
        public readonly ?string $source,
        public readonly int $characters,
        public readonly int $cacheHits,
        public readonly int $requests,
    ) {}
}
