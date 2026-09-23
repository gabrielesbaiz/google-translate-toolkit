<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Illuminate\Support\Collection;

if (! function_exists('google_translate')) {
    /**
     * Translate text, or grab the toolkit itself when called without arguments.
     *
     * @param  string|iterable<array-key, string>|null  $text
     * @param  Language|string|iterable<int, Language|string>|null  $to
     * @return GoogleTranslateToolkit|Translation|TranslationCollection|Collection<string, mixed>
     */
    function google_translate(
        string|iterable|null $text = null,
        Language|string|iterable|null $to = null,
        Language|string|null $from = null,
    ): GoogleTranslateToolkit|Translation|TranslationCollection|Collection {
        $toolkit = app(GoogleTranslateToolkit::class);

        if ($text === null) {
            return $toolkit;
        }

        return $toolkit->translate($text, $from, $to);
    }
}
