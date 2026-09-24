<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Concerns;

use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Jobs\TranslateAttributesJob;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Mirrors a column into a sibling column per locale, so "body" translated into
 * Italian is written to "body_it". Pair it with the Translatable contract.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTranslations
{
    /**
     * Get the attributes that should be translated.
     *
     * @return array<int, string>
     */
    public function translatableAttributes(): array
    {
        return [];
    }

    /**
     * Get the name of the column holding the translation of the given attribute.
     */
    public function translatedAttributeName(string $attribute, Language|string|null $locale = null): string
    {
        $locale = Language::normalize($locale ?? app(Config::class)->defaultTarget());

        $suffix = str_replace(
            ['{locale}', '{LOCALE}'],
            [Str::slug($locale, '_'), Str::upper(Str::slug($locale, '_'))],
            app(Config::class)->attributeSuffix(),
        );

        return $attribute.$suffix;
    }

    /**
     * Get the translated value of the given attribute.
     */
    public function getTranslatedAttribute(string $attribute, Language|string|null $locale = null): ?string
    {
        $column = $this->translatedAttributeName($attribute, $locale);

        return filled($this->{$column}) ? (string) $this->{$column} : null;
    }

    /**
     * Translate the given attributes into their sibling columns without saving.
     *
     * @param  array<int, string>|null  $attributes
     */
    public function translateAttributes(
        Language|string|null $to = null,
        Language|string|null $from = null,
        ?array $attributes = null,
        bool $overwrite = false,
    ): static {
        $attributes = $attributes ?? $this->translatableAttributes();

        $pending = GoogleTranslateToolkit::query()->when($to !== null, fn ($query) => $query->to($to));

        if ($from !== null) {
            $pending = $pending->from($from);
        }

        $sources = [];

        foreach ($attributes as $attribute) {
            $value = $this->{$attribute};
            $column = $this->translatedAttributeName($attribute, $to);

            if (blank($value) || (! $overwrite && filled($this->{$column}))) {
                continue;
            }

            $sources[$column] = (string) $value;
        }

        if ($sources === []) {
            return $this;
        }

        /** @var TranslationCollection $translations */
        $translations = $pending->many(array_values($sources));

        foreach (array_keys($sources) as $position => $column) {
            $translation = $translations->get($position);

            if ($translation !== null) {
                $this->{$column} = $translation->translatedText;
            }
        }

        return $this;
    }

    /**
     * Queue the translation of the given attributes.
     *
     * @param  array<int, string>|null  $attributes
     */
    public function queueTranslateAttributes(
        Language|string|null $to = null,
        Language|string|null $from = null,
        ?array $attributes = null,
        bool $overwrite = false,
    ): void {
        $config = app(Config::class);

        $job = TranslateAttributesJob::dispatch(
            $this,
            $to === null ? null : Language::normalize($to),
            $from === null ? null : Language::normalize($from),
            $attributes,
            $overwrite,
        );

        if ($connection = $config->queueConnection()) {
            $job->onConnection($connection);
        }

        if ($queue = $config->queueName()) {
            $job->onQueue($queue);
        }
    }

    /**
     * Scope the query to rows whose source column has content but whose translation is empty.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function scopeWhereTranslationMissing(Builder $query, string $attribute, Language|string|null $locale = null): Builder
    {
        $column = $this->translatedAttributeName($attribute, $locale);

        return $query
            ->whereNotNull($attribute)
            ->where($attribute, '!=', '')
            ->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, ''));
    }
}
