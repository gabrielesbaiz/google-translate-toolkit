<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\RateLimitExceededException;
use Illuminate\Cache\RateLimiter;

final class Throttle
{
    public function __construct(
        private readonly Config $config,
        private readonly RateLimiter $limiter,
    ) {}

    /**
     * Reserve one slot per outgoing API request.
     */
    public function hit(int $requests = 1): void
    {
        if (! $this->config->rateLimitEnabled()) {
            return;
        }

        $key = $this->config->rateLimitKey();
        $max = $this->config->rateLimitPerMinute();

        for ($i = 0; $i < $requests; $i++) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                throw new RateLimitExceededException($this->limiter->availableIn($key));
            }

            $this->limiter->hit($key, 60);
        }
    }

    public function clear(): void
    {
        $this->limiter->clear($this->config->rateLimitKey());
    }
}
