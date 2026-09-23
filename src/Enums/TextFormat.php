<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Enums;

use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\InvalidFormatException;

enum TextFormat: string
{
    case Text = 'text';
    case Html = 'html';

    public static function make(self|string|null $format, self $default = self::Text): self
    {
        if ($format instanceof self) {
            return $format;
        }

        if (blank($format)) {
            return $default;
        }

        return self::tryFrom(mb_strtolower(trim($format))) ?? throw InvalidFormatException::make($format);
    }

    public function isHtml(): bool
    {
        return $this === self::Html;
    }
}
