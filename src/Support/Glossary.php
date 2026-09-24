<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Illuminate\Support\Str;

/**
 * Shields brand names from translation and forces domain wording per locale.
 */
final class Glossary
{
    /**
     * Create a new glossary instance.
     */
    public function __construct(private readonly Config $config) {}

    /**
     * Get a regular expression for every protected term.
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
     * Apply the glossary overrides for the given locale to a translated string.
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

    /**
     * Give the replacement the same capitalization as the term it replaces.
     */
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
