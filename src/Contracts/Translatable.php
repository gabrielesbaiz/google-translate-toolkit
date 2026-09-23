<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Contracts;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implemented for free by the HasTranslations trait.
 */
interface Translatable
{
    /** @return array<int, string> */
    public function translatableAttributes(): array;

    public function translatedAttributeName(string $attribute, Language|string|null $locale = null): string;

    public function getTranslatedAttribute(string $attribute, Language|string|null $locale = null): ?string;

    /** @param array<int, string>|null $attributes */
    public function translateAttributes(
        Language|string|null $to = null,
        Language|string|null $from = null,
        ?array $attributes = null,
        bool $overwrite = false,
    ): static;

    /** @param array<int, string>|null $attributes */
    public function queueTranslateAttributes(
        Language|string|null $to = null,
        Language|string|null $from = null,
        ?array $attributes = null,
        bool $overwrite = false,
    ): void;

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function scopeWhereTranslationMissing(Builder $query, string $attribute, Language|string|null $locale = null): Builder;
}
