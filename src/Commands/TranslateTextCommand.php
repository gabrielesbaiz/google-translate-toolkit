<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Commands;

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Console\Command;

class TranslateTextCommand extends Command
{
    protected $signature = 'translate:text
        {text* : One or more strings to translate}
        {--from= : Source language code (auto-detected when omitted)}
        {--to=* : One or more target language codes}
        {--format=text : text or html}
        {--no-cache : Bypass the translation cache}
        {--dry-run : Only report what the call would cost}
        {--json : Output raw JSON}';

    protected $description = 'Translate text with the Google Translate API';

    public function handle(GoogleTranslateToolkit $toolkit): int
    {
        /** @var array<int, string> $texts */
        $texts = (array) $this->argument('text');

        $pending = $toolkit->query()
            ->when($this->option('from'), fn ($query) => $query->from((string) $this->option('from')))
            ->when($this->option('to'), fn ($query) => $query->to((array) $this->option('to')))
            ->format((string) $this->option('format'))
            ->when($this->option('no-cache'), fn ($query) => $query->withoutCache());

        if ($this->option('dry-run')) {
            $this->table(['metric', 'value'], collect($pending->estimate($texts)->toArray())->map(
                fn (mixed $value, string $key) => [$key, (string) $value],
            )->values()->all());

            return self::SUCCESS;
        }

        $result = $pending->many($texts);

        $rows = collect($result)
            ->flatMap(fn (mixed $value) => $value instanceof Translation ? [$value] : collect($value)->all())
            ->map(fn (Translation $translation) => [
                $translation->sourceLanguage ?? 'auto',
                $translation->targetLanguage,
                $translation->sourceText,
                $translation->translatedText,
                $translation->cached ? 'cache' : 'api',
            ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(['from', 'to', 'source', 'translation', 'origin'], $rows->all());

        return self::SUCCESS;
    }
}
