<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

class UnsupportedLanguageException extends GoogleTranslateException
{
    /**
     * Create a new exception for the given language code.
     */
    public static function make(string $code): self
    {
        return new self(sprintf(
            'Unsupported language code [%s]. Run "php artisan translate:languages" for the supported list, or set "strict_languages" to false in config/google-translate-toolkit.php.',
            $code,
        ));
    }
}
