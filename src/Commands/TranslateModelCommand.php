<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translatable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TranslateModelCommand extends Command
{
    protected $signature = 'translate:model
        {model : Fully qualified model class}
        {--to= : Target locale (defaults to the configured one)}
        {--from= : Source locale (auto-detected when omitted)}
        {--attributes=* : Limit to these attributes}
        {--chunk=200 : Rows per chunk}
        {--limit= : Stop after this many rows}
        {--queue : Dispatch a job per row instead of translating inline}
        {--overwrite : Retranslate rows that already have a value}';

    protected $description = 'Backfill the translated columns of a model that uses HasTranslations';

    public function handle(): int
    {
        /** @var class-string<Model> $class */
        $class = (string) $this->argument('model');

        if (! class_exists($class)) {
            $this->components->error(sprintf('Model [%s] not found.', $class));

            return self::FAILURE;
        }

        $model = new $class;

        if (! $model instanceof Translatable) {
            $this->components->error(sprintf(
                '[%s] must use the HasTranslations trait and implement the %s contract.',
                $class,
                Translatable::class,
            ));

            return self::FAILURE;
        }

        $attributes = (array) $this->option('attributes');
        $attributes = $attributes === [] ? $model->translatableAttributes() : $attributes;

        if ($attributes === []) {
            $this->components->error(sprintf('[%s] declares no $translatable attributes.', $class));

            return self::FAILURE;
        }

        $locale = $this->option('to') ? (string) $this->option('to') : null;
        $query = $this->query($model, $attributes, $locale);
        $total = (int) $query->toBase()->getCountForPagination();

        if ($limit = $this->option('limit')) {
            $total = min($total, (int) $limit);
        }

        if ($total === 0) {
            $this->components->info('Nothing to translate.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Translating %d %s rows into %s.', $total, class_basename($class), $locale ?? 'the default locale'));

        $bar = $this->output->createProgressBar($total);
        $processed = 0;

        $query->chunkById((int) $this->option('chunk'), function (Collection $rows) use ($locale, $attributes, $bar, &$processed, $total): bool {
            /** @var Translatable&Model $row */
            foreach ($rows as $row) {
                if ($processed >= $total) {
                    return false;
                }

                if ($this->option('queue')) {
                    $row->queueTranslateAttributes($locale, $this->option('from'), $attributes, (bool) $this->option('overwrite'));
                } else {
                    $row->translateAttributes($locale, $this->option('from'), $attributes, (bool) $this->option('overwrite'))->save();
                }

                $processed++;
                $bar->advance();
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);
        $this->components->info(sprintf('%d rows %s.', $processed, $this->option('queue') ? 'queued' : 'translated'));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $attributes
     * @return Builder<Model>
     */
    private function query(Translatable&Model $model, array $attributes, ?string $locale): Builder
    {
        /** @var Builder<Model> $query */
        $query = $model->newQuery();

        if ($this->option('overwrite')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($model, $attributes, $locale): void {
            foreach ($attributes as $attribute) {
                $query->orWhere(fn (Builder $inner) => $model->scopeWhereTranslationMissing($inner, $attribute, $locale));
            }
        });
    }
}
