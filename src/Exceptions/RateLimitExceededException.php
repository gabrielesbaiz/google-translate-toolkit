<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Exceptions;

class RateLimitExceededException extends GoogleTranslateException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(public readonly int $secondsUntilAvailable = 60)
    {
        parent::__construct(sprintf('Google Translate rate limit reached. Retry in %d second(s).', $secondsUntilAvailable));
    }
}
