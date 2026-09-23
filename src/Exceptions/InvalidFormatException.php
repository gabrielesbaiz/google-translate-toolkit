<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

class InvalidFormatException extends GoogleTranslateException
{
    public static function make(string $format): self
    {
        return new self(sprintf('Invalid text format [%s]. Supported formats are "text" and "html".', $format));
    }
}
