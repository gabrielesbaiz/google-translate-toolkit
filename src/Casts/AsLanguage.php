<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Casts;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Language|null, Language|string|null>
 */
class AsLanguage implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Language
    {
        return $value === null ? null : Language::tryFromCode((string) $value);
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Language::fromCode(is_string($value) ? $value : $value->value)->value;
    }
}
