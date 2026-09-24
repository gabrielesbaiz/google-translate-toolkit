<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

/**
 * Splits a batch of segments into requests the v2 endpoint will accept.
 */
final class Chunker
{
    /**
     * Create a new chunker instance.
     */
    public function __construct(private readonly Config $config) {}

    /**
     * Split the given segments into chunks, preserving their original keys.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, string>>
     */
    public function chunk(array $texts, ?int $maxSegments = null, ?int $maxCharacters = null): array
    {
        $maxSegments ??= $this->config->maxSegments();
        $maxCharacters ??= $this->config->maxCharacters();

        $chunks = [];
        $current = [];
        $characters = 0;

        foreach ($texts as $index => $text) {
            $length = mb_strlen($text);

            $exceedsSegments = count($current) >= $maxSegments;
            $exceedsCharacters = $current !== [] && ($characters + $length) > $maxCharacters;

            if ($exceedsSegments || $exceedsCharacters) {
                $chunks[] = $current;
                $current = [];
                $characters = 0;
            }

            $current[$index] = $text;
            $characters += $length;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
