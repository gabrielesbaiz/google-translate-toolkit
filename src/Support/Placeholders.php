<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;

/**
 * Hides the tokens Google must not touch behind sentinels, then puts them back.
 *
 * Translation placeholders, Blade expressions, format specifiers, URLs, e-mail
 * addresses, handles and code spans are all masked before a request is sent.
 */
final class Placeholders
{
    /**
     * The built-in patterns, greediest first so they win over the narrower ones.
     *
     * @var array<int, string>
     */
    public const PATTERNS = [
        '/<code\b[^>]*>.*?<\/code>/is',
        '/`[^`\n]+`/',
        '/\{\{--.*?--\}\}/s',
        '/\{\{.*?\}\}/s',
        '/\{[A-Za-z0-9_.]+\}/',
        '/\bhttps?:\/\/[^\s<>"\']+/i',
        '/[\w.+-]+@[\w-]+\.[\w.-]+/',
        '/(?<![\w:]):[A-Za-z_][A-Za-z0-9_]*/',
        '/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]/',
        '/(?<![\w@])@[A-Za-z0-9_]{2,}/',
    ];

    /**
     * The additional patterns supplied by the caller.
     *
     * @var array<int, string>
     */
    private array $extra = [];

    /**
     * Create a new placeholders instance.
     */
    public function __construct(private readonly Config $config) {}

    /**
     * Create a copy of the instance with the given patterns added.
     *
     * @param  array<int, string>  $patterns  additional regular expressions or literal terms
     */
    public function withPatterns(array $patterns): self
    {
        $clone = clone $this;
        $clone->extra = array_values(array_unique([...$this->extra, ...$patterns]));

        return $clone;
    }

    /**
     * Replace every protected token in the text with a sentinel.
     */
    public function mask(string $text, TextFormat $format = TextFormat::Text): MaskedText
    {
        if (! $this->config->placeholdersEnabled() && $this->extra === []) {
            return new MaskedText($text);
        }

        $map = [];
        $index = 0;

        foreach ($this->patterns() as $pattern) {
            $text = (string) preg_replace_callback($pattern, function (array $matches) use (&$map, &$index, $format): string {
                $sentinel = $this->sentinel($index++, $format);
                $map[$sentinel] = $matches[0];

                return $sentinel;
            }, $text);
        }

        return new MaskedText($text, $map);
    }

    /**
     * Put the original tokens back in place of their sentinels.
     *
     * @param  array<string, string>  $map
     */
    public function restore(string $text, array $map): string
    {
        if ($map === []) {
            return $text;
        }

        foreach ($map as $sentinel => $original) {
            if (str_contains($text, $sentinel)) {
                $text = str_replace($sentinel, $original, $text);

                continue;
            }

            // Google occasionally reformats a sentinel it was handed. Here we will
            // match the sentinel loosely on its index alone so the original token
            // still makes it back into the translated string.
            $index = (string) filter_var($sentinel, FILTER_SANITIZE_NUMBER_INT);

            $text = (string) preg_replace(
                '/⟦\s*'.preg_quote($index, '/').'\s*⟧|<\s*span[^>]*translate\s*=\s*"?no"?[^>]*>\s*'.preg_quote($index, '/').'\s*<\s*\/\s*span\s*>/iu',
                str_replace('\\', '\\\\', str_replace('$', '\$', $original)),
                $text,
                1,
            );
        }

        return $text;
    }

    /**
     * Mask a whole batch of texts, keeping the original indexes aligned.
     *
     * @param  array<int, string>  $texts
     * @return array<int, MaskedText>
     */
    public function maskAll(array $texts, TextFormat $format = TextFormat::Text): array
    {
        return array_map(fn (string $text) => $this->mask($text, $format), $texts);
    }

    /**
     * Build the sentinel standing in for the token at the given index.
     */
    private function sentinel(int $index, TextFormat $format): string
    {
        return $format->isHtml()
            ? '<span translate="no">'.$index.'</span>'
            : '⟦'.$index.'⟧';
    }

    /**
     * Get every pattern to mask, built-in and configured alike.
     *
     * @return array<int, string>
     */
    private function patterns(): array
    {
        $configured = $this->config->placeholdersEnabled() ? self::PATTERNS : [];

        return array_map(
            fn (string $pattern) => $this->isRegex($pattern) ? $pattern : '/'.preg_quote($pattern, '/').'/u',
            [...$configured, ...$this->config->placeholderPatterns(), ...$this->extra],
        );
    }

    /**
     * Determine if the given pattern is already a regular expression.
     */
    private function isRegex(string $pattern): bool
    {
        return (bool) preg_match('/^([\/#~%]).*\1[imsxuADSUXJn]*$/s', $pattern);
    }
}
