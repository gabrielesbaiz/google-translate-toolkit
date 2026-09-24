<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Console\Command;

class TranslateStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate:stats {--days=7 : How many days to report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show translation volume, cache hit rate and estimated spend';

    /**
     * Execute the console command.
     */
    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        $stats = $toolkit->stats(max(1, (int) $this->option('days')));

        $this->table(
            ['date', 'calls', 'characters', 'cache hits', 'hit rate', 'failures', 'cost'],
            $stats->map(fn (array $row, string $date) => [
                $date,
                (string) $row['calls'],
                number_format((float) $row['characters']),
                (string) $row['cache_hits'],
                $row['hit_rate'].'%',
                (string) $row['failures'],
                (string) $row['cost'],
            ])->values()->all(),
        );

        $this->line(sprintf(
            '  <fg=gray>total: %s characters, %s calls</>',
            number_format((float) $stats->sum('characters')),
            number_format((float) $stats->sum('calls')),
        ));

        return self::SUCCESS;
    }
}
