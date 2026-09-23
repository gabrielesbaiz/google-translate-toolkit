<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;

/**
 * Hides tokens Google must not touch - ":name", "{count}", "{{ blade }}", "%s",
 * URLs, e-mails, handles and code spans - behind sentinels, then puts them back.
 */
final class Placeholders
{
    /** Ordered: the greediest patterns run first so they win over the narrower ones. */
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

    /** @var array<int, string> */
    private array $extra = [];

    public function __construct(private readonly Config $config) {}

    /**
     * @param  array<int, string>  $patterns  additional regular expressions or literal terms
     */
    public function withPatterns(array $patterns): self
    {
        $clone = clone $this;
        $clone->extra = array_values(array_unique([...$this->extra, ...$patterns]));

        return $clone;
    }

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

            // Google occasionally reformats the sentinel: recover it loosely.
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
     * Mask a whole batch, keeping index alignment.
     *
     * @param  array<int, string>  $texts
     * @return array<int, MaskedText>
     */
    public function maskAll(array $texts, TextFormat $format = TextFormat::Text): array
    {
        return array_map(fn (string $text) => $this->mask($text, $format), $texts);
    }

    private function sentinel(int $index, TextFormat $format): string
    {
        return $format->isHtml()
            ? '<span translate="no">'.$index.'</span>'
            : '⟦'.$index.'⟧';
    }

    /** @return array<int, string> */
    private function patterns(): array
    {
        $configured = $this->config->placeholdersEnabled() ? self::PATTERNS : [];

        return array_map(
            fn (string $pattern) => $this->isRegex($pattern) ? $pattern : '/'.preg_quote($pattern, '/').'/u',
            [...$configured, ...$this->config->placeholderPatterns(), ...$this->extra],
        );
    }

    private function isRegex(string $pattern): bool
    {
        return (bool) preg_match('/^([\/#~%]).*\1[imsxuADSUXJn]*$/s', $pattern);
    }
}
