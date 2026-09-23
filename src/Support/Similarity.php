<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Illuminate\Support\Str;

final class Similarity
{
    /**
     * 0 (unrelated) to 1 (identical), blending character overlap and edit distance.
     */
    public static function score(string $first, string $second): float
    {
        $a = self::normalize($first);
        $b = self::normalize($second);

        if ($a === '' && $b === '') {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        $left = mb_substr($a, 0, 255);
        $right = mb_substr($b, 0, 255);
        $distance = levenshtein($left, $right);
        $longest = max(mb_strlen($left), mb_strlen($right), 1);

        return round(min(1.0, (($percent / 100) + (1 - min(1, $distance / $longest))) / 2), 4);
    }

    private static function normalize(string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
