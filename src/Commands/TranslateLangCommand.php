<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\LangFiles;
use Illuminate\Console\Command;

class TranslateLangCommand extends Command
{
    protected $signature = 'translate:lang
        {--from=en : Source locale directory}
        {--to=* : Target locales}
        {--group=* : Only these groups (file names, or __json)}
        {--force : Overwrite lines that already exist in the target}
        {--dry-run : Report what would be translated and what it would cost}';

    protected $description = 'Translate lang files into other locales, skipping lines that already exist';

    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        $lang = LangFiles::make();
        $from = (string) $this->option('from');
        $targets = (array) $this->option('to');

        if ($targets === []) {
            $this->components->error('Provide at least one --to locale.');

            return self::FAILURE;
        }

        $source = $lang->read($from);

        if ($source->isEmpty()) {
            $this->components->error(sprintf('No lang files found for [%s] in %s.', $from, $lang->path()));

            return self::FAILURE;
        }

        $groups = (array) $this->option('group');

        foreach ($targets as $target) {
            $existing = $lang->read($target);
            $written = 0;
            $skipped = 0;
            $pending = [];

            foreach ($source as $group => $lines) {
                if ($groups !== [] && ! in_array($group, $groups, true)) {
                    continue;
                }

                $current = $existing[$group] ?? [];

                foreach ($lines as $key => $value) {
                    if (! $this->option('force') && filled($current[$key] ?? null)) {
                        $skipped++;

                        continue;
                    }

                    $pending[$group][$key] = $value;
                }
            }

            if ($pending === []) {
                $this->components->info(sprintf('[%s] nothing to translate (%d lines already present).', $target, $skipped));

                continue;
            }

            $flat = collect($pending)->flatMap(fn (array $lines, string $group) => collect($lines)
                ->mapWithKeys(fn (string $value, string $key) => [$group.'|'.$key => $value]))->all();

            if ($this->option('dry-run')) {
                $estimate = $toolkit->estimate(array_values($flat), [$target]);

                $this->components->info(sprintf(
                    '[%s] %d lines would be translated (%s characters, %s).',
                    $target,
                    count($flat),
                    number_format($estimate->characters),
                    $estimate->formattedCost(),
                ));

                continue;
            }

            /** @var TranslationCollection $translations */
            $translations = $toolkit->from($from)->to($target)->preserving()->many(array_values($flat));

            $byKey = array_combine(array_keys($flat), $translations->texts()->all());

            foreach ($pending as $group => $lines) {
                $merged = ($existing[$group] ?? []);

                foreach (array_keys($lines) as $key) {
                    $merged[$key] = $byKey[$group.'|'.$key] ?? $lines[$key];
                    $written++;
                }

                ksort($merged);

                $path = $lang->write($target, $group, $merged);

                $this->components->twoColumnDetail($path, sprintf('%d lines', count($lines)));
            }

            $this->components->info(sprintf('[%s] %d lines translated, %d kept.', $target, $written, $skipped));
        }

        return self::SUCCESS;
    }
}
