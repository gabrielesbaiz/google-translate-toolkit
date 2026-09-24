<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Illuminate\Contracts\Config\Repository;

final readonly class Config
{
    public const KEY = 'google-translate-toolkit';

    /**
     * Create a new configuration instance.
     */
    public function __construct(private Repository $repository) {}

    /**
     * Get the given configuration value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get(self::KEY.'.'.$key, $default);
    }

    /**
     * Set the given configuration value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->repository->set(self::KEY.'.'.$key, $value);
    }

    /**
     * Get the Google Translate API key.
     */
    public function apiKey(): ?string
    {
        $key = $this->get('api_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * Get the base URL of the Cloud Translation endpoint.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->get('base_url', 'https://translation.googleapis.com/language/translate/v2'), '/');
    }

    /**
     * Get the default source language, or null to let Google detect it.
     *
     * The 1.x key name is still honored so published config files keep working.
     */
    public function defaultSource(): ?string
    {
        $source = $this->get('default_source') ?? $this->get('default_source_translation');

        return filled($source) ? (string) $source : null;
    }

    /**
     * Get the default target language, falling back to the application locale.
     */
    public function defaultTarget(): string
    {
        $target = $this->get('default_target') ?? $this->get('default_target_translation');

        return filled($target) ? (string) $target : (string) $this->repository->get('app.locale', 'en');
    }

    /**
     * Get the default text format.
     */
    public function defaultFormat(): string
    {
        return (string) ($this->get('default_format') ?? 'text');
    }

    /**
     * Determine if unknown language codes should be rejected.
     */
    public function strictLanguages(): bool
    {
        return (bool) $this->get('strict_languages', true);
    }

    /**
     * Determine if a failing call should return the source text.
     */
    public function fallbackToSource(): bool
    {
        return (bool) $this->get('fallback_to_source', false);
    }

    /**
     * Determine if the package events should be dispatched.
     */
    public function eventsEnabled(): bool
    {
        return (bool) $this->get('events', true);
    }

    /**
     * Get the request timeout in seconds.
     */
    public function timeout(): int
    {
        return (int) $this->get('http.timeout', 10);
    }

    /**
     * Get the connection timeout in seconds.
     */
    public function connectTimeout(): int
    {
        return (int) $this->get('http.connect_timeout', 5);
    }

    /**
     * Get the retry settings for outgoing requests.
     *
     * @return array{times: int, sleep: int, backoff: bool}
     */
    public function retry(): array
    {
        return [
            'times' => max(1, (int) $this->get('http.retry.times', 3)),
            'sleep' => max(0, (int) $this->get('http.retry.sleep', 250)),
            'backoff' => (bool) $this->get('http.retry.backoff', true),
        ];
    }

    /**
     * Get the number of requests to send concurrently.
     */
    public function concurrency(): int
    {
        return max(1, (int) $this->get('http.concurrency', 5));
    }

    /**
     * Get the maximum number of segments per request.
     */
    public function maxSegments(): int
    {
        return max(1, (int) $this->get('chunk.max_segments', 100));
    }

    /**
     * Get the maximum number of characters per request.
     */
    public function maxCharacters(): int
    {
        return max(1, (int) $this->get('chunk.max_characters', 20000));
    }

    /**
     * Determine if translations should be cached.
     */
    public function cacheEnabled(): bool
    {
        return (bool) $this->get('cache.enabled', true);
    }

    /**
     * Get the cache store translations are written to.
     */
    public function cacheStore(): ?string
    {
        $store = $this->get('cache.store');

        return filled($store) ? (string) $store : null;
    }

    /**
     * Get the number of seconds a cached translation stays fresh.
     */
    public function cacheTtl(): int
    {
        return (int) $this->get('cache.ttl', 60 * 60 * 24 * 30);
    }

    /**
     * Get the prefix applied to every cache key.
     */
    public function cachePrefix(): string
    {
        return (string) $this->get('cache.prefix', 'gtt');
    }

    /**
     * Determine if outgoing requests should be rate limited.
     */
    public function rateLimitEnabled(): bool
    {
        return (bool) $this->get('rate_limit.enabled', false);
    }

    /**
     * Get the rate limiter key.
     */
    public function rateLimitKey(): string
    {
        return (string) $this->get('rate_limit.key', 'google-translate-toolkit');
    }

    /**
     * Get the number of requests allowed each minute.
     */
    public function rateLimitPerMinute(): int
    {
        return max(1, (int) $this->get('rate_limit.max_per_minute', 600));
    }

    /**
     * Get the connection the package jobs are dispatched on.
     */
    public function queueConnection(): ?string
    {
        $connection = $this->get('queue.connection');

        return filled($connection) ? (string) $connection : null;
    }

    /**
     * Get the queue the package jobs are dispatched to.
     */
    public function queueName(): ?string
    {
        $queue = $this->get('queue.queue');

        return filled($queue) ? (string) $queue : null;
    }

    /**
     * Get the suffix appended to translated model attributes.
     */
    public function attributeSuffix(): string
    {
        return (string) $this->get('attribute_suffix', '_{locale}');
    }

    /**
     * Determine if the built-in placeholder patterns should be applied.
     */
    public function placeholdersEnabled(): bool
    {
        return (bool) $this->get('placeholders.enabled', true);
    }

    /**
     * Get the additional placeholder patterns to protect.
     *
     * @return array<int, string>
     */
    public function placeholderPatterns(): array
    {
        return array_values((array) $this->get('placeholders.patterns', []));
    }

    /**
     * Get the terms that must never be translated.
     *
     * @return array<int, string>
     */
    public function protectedTerms(): array
    {
        return array_values(array_filter((array) $this->get('glossary.protect', [])));
    }

    /**
     * Get the glossary overrides for the given locale.
     *
     * @return array<string, string>
     */
    public function glossaryOverrides(string $locale): array
    {
        $overrides = (array) $this->get('glossary.overrides', []);

        return (array) ($overrides[$locale] ?? $overrides[explode('-', $locale)[0]] ?? []);
    }

    /**
     * Get the price charged per million characters.
     */
    public function pricePerMillion(): float
    {
        return (float) $this->get('pricing.per_million', 20.00);
    }

    /**
     * Get the currency prices are expressed in.
     */
    public function currency(): string
    {
        return (string) $this->get('pricing.currency', 'USD');
    }

    /**
     * Get the maximum number of characters billable in a day.
     */
    public function dailyCharacterBudget(): ?int
    {
        $budget = $this->get('budget.max_characters_per_day');

        return filled($budget) ? (int) $budget : null;
    }

    /**
     * Determine if usage statistics should be recorded.
     */
    public function statsEnabled(): bool
    {
        return (bool) $this->get('stats.enabled', true);
    }

    /**
     * Get the number of days usage statistics are kept.
     */
    public function statsRetentionDays(): int
    {
        return max(1, (int) $this->get('stats.retention_days', 30));
    }

    /**
     * Determine if the response middleware should translate anything.
     */
    public function middlewareEnabled(): bool
    {
        return (bool) $this->get('middleware.enabled', false);
    }

    /**
     * Get the response fields the middleware may translate.
     *
     * @return array<int, string>
     */
    public function middlewareFields(): array
    {
        return array_values((array) $this->get('middleware.fields', []));
    }

    /**
     * Get the locales the middleware may negotiate.
     *
     * @return array<int, string>
     */
    public function middlewareLocales(): array
    {
        return array_values((array) $this->get('middleware.locales', []));
    }
}
