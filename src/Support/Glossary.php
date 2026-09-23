<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Illuminate\Support\Str;

/**
 * Brand names that must survive untranslated, plus forced domain wording per locale.
 */
final class Glossary
{
    public function __construct(private readonly Config $config) {}

    /**
     * Regular expressions for every protected term, ready for the masker.
     *
     * @return array<int, string>
     */
    public function protectedPatterns(): array
    {
        return array_map(
            fn (string $term) => '/(?<!\w)'.preg_quote($term, '/').'(?!\w)/u',
            $this->config->protectedTerms(),
        );
    }

    /**
     * Apply the locale overrides to a translated string, preserving capitalisation.
     */
    public function apply(string $translated, string $locale): string
    {
        foreach ($this->config->glossaryOverrides($locale) as $search => $replace) {
            $translated = (string) preg_replace_callback(
                '/(?<!\w)'.preg_quote((string) $search, '/').'(?!\w)/iu',
                fn (array $matches) => $this->matchCase($matches[0], (string) $replace),
                $translated,
            );
        }

        return $translated;
    }

    private function matchCase(string $original, string $replacement): string
    {
        if (Str::upper($original) === $original && Str::length($original) > 1) {
            return Str::upper($replacement);
        }

        if (Str::ucfirst(Str::lower($original)) === $original) {
            return Str::ucfirst($replacement);
        }

        return $replacement;
    }
}
