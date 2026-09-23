<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Jobs;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WarmTranslationCacheJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $texts
     * @param  array<int, string>  $targets
     */
    public function __construct(
        public readonly array $texts,
        public readonly array $targets,
        public readonly ?string $source = null,
    ) {}

    public function handle(GoogleTranslateToolkit $toolkit): void
    {
        $toolkit->from($this->source)->to($this->targets)->many($this->texts);
    }
}
