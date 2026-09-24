<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Contracts;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * The contract fulfilled by the HasTranslations trait.
 */
interface Translatable
{
    /**
     * Get the attributes that should be translated.
     *
     * @return array<int, string>
     */
    public function translatableAttributes(): array;

    /**
     * Get the name of the column holding the translation of the given attribute.
     */
    public function translatedAttributeName(string $attribute, Language|string|null $locale = null): string;

    /**
     * Get the translated value of the given attribute.
     */
    public function getTranslatedAttribute(string $attribute, Language|string|null $locale = null): ?string;

    /**
     * Translate the given attributes into their sibling columns.
     *
     * @param  array<int, string>|null  $attributes
     */
    public function translateAttributes(
        Language|string|null $to = null,
        Language|string|null $from = null,
        ?array $attributes = null,
        bool $overwrite = false,
    ): static;

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
    ): void;

    /**
     * Scope the query to rows whose translation is still missing.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function scopeWhereTranslationMissing(Builder $query, string $attribute, Language|string|null $locale = null): Builder;
}
