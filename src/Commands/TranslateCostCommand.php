<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslateCostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate:cost
        {text?* : Strings to price}
        {--file= : Price the contents of a file instead}
        {--to=* : Target languages (each one multiplies the cost)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Estimate what a translation would cost before spending anything';

    /**
     * Execute the console command.
     */
    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        $texts = (array) $this->argument('text');

        if ($file = $this->option('file')) {
            if (! File::exists((string) $file)) {
                $this->components->error(sprintf('File [%s] not found.', $file));

                return self::FAILURE;
            }

            $texts = array_values(array_filter(preg_split('/\R/', File::get((string) $file)) ?: []));
        }

        if ($texts === []) {
            $this->components->error('Provide text arguments or --file.');

            return self::FAILURE;
        }

        $estimate = $toolkit->estimate($texts, (array) $this->option('to'));

        $this->table(['metric', 'value'], [
            ['segments', (string) $estimate->segments],
            ['characters', number_format($estimate->characters)],
            ['billable characters', number_format($estimate->billableCharacters)],
            ['already cached', (string) $estimate->cachedSegments],
            ['requests', (string) $estimate->requests],
            ['targets', (string) $estimate->targets],
            ['estimated cost', $estimate->formattedCost()],
        ]);

        return self::SUCCESS;
    }
}
