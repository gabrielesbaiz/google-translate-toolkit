<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use Illuminate\Support\Collection;

/**
 * @extends Collection<array-key, Translation>
 */
final class TranslationCollection extends Collection
{
    /**
     * Get the translated text of every translation in the collection.
     *
     * @return Collection<array-key, string>
     */
    public function texts(): Collection
    {
        return new Collection(array_map(
            fn (Translation $translation): string => $translation->translatedText,
            $this->all(),
        ));
    }

    /**
     * Get the collection keyed by source text and valued by translated text.
     *
     * @return Collection<string, string>
     */
    public function dictionary(): Collection
    {
        $dictionary = [];

        foreach ($this->all() as $translation) {
            $dictionary[$translation->sourceText] = $translation->translatedText;
        }

        return new Collection($dictionary);
    }

    /**
     * Get the translated text of every translation as a list.
     *
     * @return array<int, string>
     */
    public function toStrings(): array
    {
        return array_values($this->texts()->all());
    }

    /**
     * Get the translated text of every translation, one per line.
     */
    public function __toString(): string
    {
        return implode(PHP_EOL, $this->toStrings());
    }
}
