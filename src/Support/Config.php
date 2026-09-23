<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Illuminate\Contracts\Config\Repository;

final readonly class Config
{
    public const KEY = 'google-translate-toolkit';

    public function __construct(private Repository $repository) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get(self::KEY.'.'.$key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->repository->set(self::KEY.'.'.$key, $value);
    }

    public function apiKey(): ?string
    {
        $key = $this->get('api_key');

        return filled($key) ? (string) $key : null;
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->get('base_url', 'https://translation.googleapis.com/language/translate/v2'), '/');
    }

    /**
     * Null means "let Google auto-detect", which is cheaper and more accurate than guessing.
     * The 1.x key name is still honoured so published config files keep working.
     */
    public function defaultSource(): ?string
    {
        $source = $this->get('default_source') ?? $this->get('default_source_translation');

        return filled($source) ? (string) $source : null;
    }

    public function defaultTarget(): string
    {
        $target = $this->get('default_target') ?? $this->get('default_target_translation');

        return filled($target) ? (string) $target : (string) $this->repository->get('app.locale', 'en');
    }

    public function defaultFormat(): string
    {
        return (string) ($this->get('default_format') ?? 'text');
    }

    public function strictLanguages(): bool
    {
        return (bool) $this->get('strict_languages', true);
    }

    public function fallbackToSource(): bool
    {
        return (bool) $this->get('fallback_to_source', false);
    }

    public function eventsEnabled(): bool
    {
        return (bool) $this->get('events', true);
    }

    public function timeout(): int
    {
        return (int) $this->get('http.timeout', 10);
    }

    public function connectTimeout(): int
    {
        return (int) $this->get('http.connect_timeout', 5);
    }

    /** @return array{times: int, sleep: int, backoff: bool} */
    public function retry(): array
    {
        return [
            'times' => max(1, (int) $this->get('http.retry.times', 3)),
            'sleep' => max(0, (int) $this->get('http.retry.sleep', 250)),
            'backoff' => (bool) $this->get('http.retry.backoff', true),
        ];
    }

    public function concurrency(): int
    {
        return max(1, (int) $this->get('http.concurrency', 5));
    }

    public function maxSegments(): int
    {
        return max(1, (int) $this->get('chunk.max_segments', 100));
    }

    public function maxCharacters(): int
    {
        return max(1, (int) $this->get('chunk.max_characters', 20000));
    }

    public function cacheEnabled(): bool
    {
        return (bool) $this->get('cache.enabled', true);
    }

    public function cacheStore(): ?string
    {
        $store = $this->get('cache.store');

        return filled($store) ? (string) $store : null;
    }

    public function cacheTtl(): int
    {
        return (int) $this->get('cache.ttl', 60 * 60 * 24 * 30);
    }

    public function cachePrefix(): string
    {
        return (string) $this->get('cache.prefix', 'gtt');
    }

    public function rateLimitEnabled(): bool
    {
        return (bool) $this->get('rate_limit.enabled', false);
    }

    public function rateLimitKey(): string
    {
        return (string) $this->get('rate_limit.key', 'google-translate-toolkit');
    }

    public function rateLimitPerMinute(): int
    {
        return max(1, (int) $this->get('rate_limit.max_per_minute', 600));
    }

    public function queueConnection(): ?string
    {
        $connection = $this->get('queue.connection');

        return filled($connection) ? (string) $connection : null;
    }

    public function queueName(): ?string
    {
        $queue = $this->get('queue.queue');

        return filled($queue) ? (string) $queue : null;
    }

    public function attributeSuffix(): string
    {
        return (string) $this->get('attribute_suffix', '_{locale}');
    }

    public function placeholdersEnabled(): bool
    {
        return (bool) $this->get('placeholders.enabled', true);
    }

    /** @return array<int, string> */
    public function placeholderPatterns(): array
    {
        return array_values((array) $this->get('placeholders.patterns', []));
    }

    /** @return array<int, string> */
    public function protectedTerms(): array
    {
        return array_values(array_filter((array) $this->get('glossary.protect', [])));
    }

    /** @return array<string, string> */
    public function glossaryOverrides(string $locale): array
    {
        $overrides = (array) $this->get('glossary.overrides', []);

        return (array) ($overrides[$locale] ?? $overrides[explode('-', $locale)[0]] ?? []);
    }

    public function pricePerMillion(): float
    {
        return (float) $this->get('pricing.per_million', 20.00);
    }

    public function currency(): string
    {
        return (string) $this->get('pricing.currency', 'USD');
    }

    public function dailyCharacterBudget(): ?int
    {
        $budget = $this->get('budget.max_characters_per_day');

        return filled($budget) ? (int) $budget : null;
    }

    public function statsEnabled(): bool
    {
        return (bool) $this->get('stats.enabled', true);
    }

    public function statsRetentionDays(): int
    {
        return max(1, (int) $this->get('stats.retention_days', 30));
    }

    public function middlewareEnabled(): bool
    {
        return (bool) $this->get('middleware.enabled', false);
    }

    /** @return array<int, string> */
    public function middlewareFields(): array
    {
        return array_values((array) $this->get('middleware.fields', []));
    }

    /** @return array<int, string> */
    public function middlewareLocales(): array
    {
        return array_values((array) $this->get('middleware.locales', []));
    }
}
