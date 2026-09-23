<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TranslateLanguagesCommand extends Command
{
    protected $signature = 'translate:languages
        {--target= : Display the language names in this language}
        {--search= : Filter by code or name}
        {--offline : Use the list bundled with the package instead of calling the API}';

    protected $description = 'List the languages supported by Google Translate';

    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        $languages = $this->option('offline')
            ? $toolkit->supportedLanguages()
            : $toolkit->languages($this->option('target') ? (string) $this->option('target') : null);

        $search = (string) ($this->option('search') ?? '');

        $rows = $languages
            ->when($search !== '', fn ($collection) => $collection->filter(
                fn (string $name, string $code) => Str::contains(Str::lower($code.' '.$name), Str::lower($search)),
            ))
            ->map(fn (string $name, string $code) => [$code, $name])
            ->values()
            ->all();

        $this->table(['code', 'name'], $rows);
        $this->line(sprintf('  <fg=gray>%d languages</>', count($rows)));

        return self::SUCCESS;
    }
}
