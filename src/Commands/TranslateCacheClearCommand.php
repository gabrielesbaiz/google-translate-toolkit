<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Console\Command;

class TranslateCacheClearCommand extends Command
{
    protected $signature = 'translate:cache-clear';

    protected $description = 'Invalidate every translation cached by this package';

    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        $version = $toolkit->flushCache();

        $this->components->info(sprintf('Translation cache invalidated (namespace version %d).', $version));

        return self::SUCCESS;
    }
}
