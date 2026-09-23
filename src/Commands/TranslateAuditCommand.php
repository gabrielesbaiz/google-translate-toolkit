<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\LangFiles;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Similarity;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TranslateAuditCommand extends Command
{
    protected $signature = 'translate:audit
        {--from=en : Source locale}
        {--to= : Locale to audit}
        {--group=* : Only these groups}
        {--threshold=0.6 : Flag lines scoring below this}
        {--limit=100 : Maximum lines to audit}';

    protected $description = 'Back-translate existing lang lines and flag the suspicious ones (costs 2 calls per line)';

    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        $lang = LangFiles::make();
        $from = (string) $this->option('from');
        $to = (string) ($this->option('to') ?? '');

        if ($to === '') {
            $this->components->error('Provide the locale to audit with --to.');

            return self::FAILURE;
        }

        $source = $lang->read($from);
        $target = $lang->read($to);
        $groups = (array) $this->option('group');
        $threshold = (float) $this->option('threshold');
        $limit = (int) $this->option('limit');

        $pairs = [];

        foreach ($source as $group => $lines) {
            if ($groups !== [] && ! in_array($group, $groups, true)) {
                continue;
            }

            foreach ($lines as $key => $value) {
                $translated = $target[$group][$key] ?? null;

                if (blank($translated) || count($pairs) >= $limit) {
                    continue;
                }

                $pairs[] = ['group' => $group, 'key' => $key, 'source' => $value, 'translated' => (string) $translated];
            }
        }

        if ($pairs === []) {
            $this->components->info('Nothing to audit.');

            return self::SUCCESS;
        }

        $estimate = $toolkit->estimate(array_column($pairs, 'translated'), [$from]);
        $this->components->info(sprintf('Auditing %d lines (~%s).', count($pairs), $estimate->formattedCost()));

        $back = $toolkit->from($to)->to($from)->many(array_column($pairs, 'translated'))->texts()->values();

        $rows = [];

        foreach ($pairs as $position => $pair) {
            $score = Similarity::score($pair['source'], (string) $back->get($position, ''));

            if ($score >= $threshold) {
                continue;
            }

            $rows[] = [
                $pair['group'].'.'.$pair['key'],
                Str::limit($pair['source'], 40),
                Str::limit($pair['translated'], 40),
                Str::limit((string) $back->get($position, ''), 40),
                (string) $score,
            ];
        }

        if ($rows === []) {
            $this->components->info(sprintf('All %d lines scored at or above %.2f.', count($pairs), $threshold));

            return self::SUCCESS;
        }

        $this->table(['key', 'source', 'translation', 'back-translation', 'score'], $rows);
        $this->components->warn(sprintf('%d of %d lines scored below %.2f.', count($rows), count($pairs), $threshold));

        return self::SUCCESS;
    }
}
