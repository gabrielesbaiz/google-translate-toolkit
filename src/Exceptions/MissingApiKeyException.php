<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

class MissingApiKeyException extends GoogleTranslateException
{
    /**
     * Create a new exception for a missing API key.
     */
    public static function make(): self
    {
        return new self('No Google Translate API key configured. Set GOOGLE_TRANSLATE_API_KEY in your .env file or "api_key" in config/google-translate-toolkit.php.');
    }
}
