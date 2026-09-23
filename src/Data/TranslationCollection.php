<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Data;

use Illuminate\Support\Collection;

/**
 * @extends Collection<array-key, Translation>
 */
final class TranslationCollection extends Collection
{
    /** @return Collection<array-key, string> */
    public function texts(): Collection
    {
        return new Collection(array_map(
            fn (Translation $translation): string => $translation->translatedText,
            $this->all(),
        ));
    }

    /**
     * Source text => translated text.
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

    /** @return array<int, string> */
    public function toStrings(): array
    {
        return array_values($this->texts()->all());
    }

    public function __toString(): string
    {
        return implode(PHP_EOL, $this->toStrings());
    }
}
