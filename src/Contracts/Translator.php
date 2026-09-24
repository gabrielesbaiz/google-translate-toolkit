<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Contracts;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;

interface Translator
{
    /**
     * Translate the given segments into the target language.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array{text: string, detectedSourceLanguage: string|null}>
     */
    public function translate(array $texts, ?string $source, string $target, TextFormat $format): array;

    /**
     * Detect the language of the given segments.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array{language: string, confidence: float, reliable: bool}>
     */
    public function detect(array $texts): array;

    /**
     * Get the languages the driver can translate, named in the given display language.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function languages(string $displayLanguage): array;

    /**
     * Translate the given segments into several target languages at once.
     *
     * @param  array<int, string>  $texts
     * @param  array<int, string>  $targets
     * @return array<string, array<int, array{text: string, detectedSourceLanguage: string|null}>>
     */
    public function translateMany(array $texts, ?string $source, array $targets, TextFormat $format): array;
}
