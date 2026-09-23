<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

class TranslationFailedException extends GoogleTranslateException
{
    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error', []);

        return new self(sprintf(
            'Google Translate API request failed [%s]: %s',
            $response->status(),
            is_array($error) ? ($error['message'] ?? $response->body()) : $response->body(),
        ), $response->status());
    }

    public static function fromThrowable(Throwable $previous): self
    {
        return new self('Google Translate API request failed: '.$previous->getMessage(), (int) $previous->getCode(), $previous);
    }

    public static function malformedResponse(): self
    {
        return new self('Google Translate API returned a malformed response.');
    }
}
