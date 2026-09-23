<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class TranslationFailed
{
    use Dispatchable;

    /** @param array<int, string> $texts */
    public function __construct(
        public readonly Throwable $exception,
        public readonly array $texts,
        public readonly string $target,
        public readonly ?string $source,
        public readonly bool $recovered = false,
    ) {}
}
