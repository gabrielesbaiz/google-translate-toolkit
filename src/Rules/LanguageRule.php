<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Rules;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Illuminate\Contracts\Validation\ValidationRule;

class LanguageRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  array<int, string>  $only
     */
    public function __construct(private readonly array $only = []) {}

    /**
     * Create a new rule instance.
     *
     * @param  array<int, string>  $only
     */
    public static function make(array $only = []): self
    {
        return new self($only);
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Language::tryFromCode($value) === null) {
            $fail('The :attribute must be a valid language code.');

            return;
        }

        if ($this->only === []) {
            return;
        }

        $normalized = Language::normalize($value);

        $allowed = array_map(fn (string $code) => Language::normalize($code), $this->only);

        if (! in_array($normalized, $allowed, true)) {
            $fail('The :attribute must be one of: '.implode(', ', $allowed).'.');
        }
    }
}
