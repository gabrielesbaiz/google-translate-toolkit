<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

class TranslationFailedException extends GoogleTranslateException
{
    /**
     * Create a new exception from the given API response.
     */
    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error', []);

        return new self(sprintf(
            'Google Translate API request failed [%s]: %s',
            $response->status(),
            is_array($error) ? ($error['message'] ?? $response->body()) : $response->body(),
        ), $response->status());
    }

    /**
     * Create a new exception from the given transport error.
     */
    public static function fromThrowable(Throwable $previous): self
    {
        return new self('Google Translate API request failed: '.$previous->getMessage(), (int) $previous->getCode(), $previous);
    }

    /**
     * Create a new exception for a response that could not be understood.
     */
    public static function malformedResponse(): self
    {
        return new self('Google Translate API returned a malformed response.');
    }
}
