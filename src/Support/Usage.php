<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Estimate;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\BudgetExceededException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Prices a call before it leaves the application and records what it spent.
 */
final class Usage
{
    /**
     * Create a new usage instance.
     */
    public function __construct(
        private readonly Config $config,
        private readonly CacheFactory $cache,
        private readonly Chunker $chunker,
    ) {}

    /**
     * Estimate what translating the given segments would cost.
     *
     * @param  iterable<int, string>|string  $texts
     * @param  array<int, string>  $targets
     */
    public function estimate(iterable|string $texts, array $targets = [], int $cachedSegments = 0): Estimate
    {
        $segments = is_string($texts) ? [$texts] : collect($texts)->map(fn ($text) => (string) $text)->values()->all();

        $targetCount = max(1, count($targets));
        $characters = array_sum(array_map(mb_strlen(...), $segments));
        $billable = $characters * $targetCount;
        $requests = count($this->chunker->chunk($segments)) * $targetCount;

        return new Estimate(
            segments: count($segments),
            characters: $characters,
            billableCharacters: $billable,
            requests: max($segments === [] ? 0 : 1, $requests),
            targets: $targetCount,
            cost: $billable / 1_000_000 * $this->config->pricePerMillion(),
            currency: $this->config->currency(),
            cachedSegments: $cachedSegments,
        );
    }

    /**
     * Guard the configured daily budget before spending any more characters.
     */
    public function guardBudget(int $characters): void
    {
        $budget = $this->config->dailyCharacterBudget();

        if ($budget === null || $characters === 0) {
            return;
        }

        $used = (int) ($this->today()['characters'] ?? 0);

        if (($used + $characters) > $budget) {
            throw BudgetExceededException::make($used, $characters, $budget);
        }
    }

    /**
     * Record what a call spent against today's usage.
     */
    public function record(int $characters, int $calls = 0, int $cacheHits = 0, int $failures = 0): void
    {
        if (! $this->config->statsEnabled()) {
            return;
        }

        $key = $this->key(Carbon::now()->toDateString());
        $store = $this->cache->store($this->config->cacheStore());

        $stats = (array) $store->get($key, []);

        $store->put($key, [
            'characters' => (int) ($stats['characters'] ?? 0) + $characters,
            'calls' => (int) ($stats['calls'] ?? 0) + $calls,
            'cache_hits' => (int) ($stats['cache_hits'] ?? 0) + $cacheHits,
            'failures' => (int) ($stats['failures'] ?? 0) + $failures,
        ], $this->config->statsRetentionDays() * 86400);
    }

    /**
     * Get the usage recorded so far today.
     *
     * @return array<string, int>
     */
    public function today(): array
    {
        return $this->forDate(Carbon::now()->toDateString());
    }

    /**
     * Get the usage recorded on the given date.
     *
     * @return array<string, int>
     */
    public function forDate(string $date): array
    {
        $stats = (array) $this->cache->store($this->config->cacheStore())->get($this->key($date), []);

        return [
            'characters' => (int) ($stats['characters'] ?? 0),
            'calls' => (int) ($stats['calls'] ?? 0),
            'cache_hits' => (int) ($stats['cache_hits'] ?? 0),
            'failures' => (int) ($stats['failures'] ?? 0),
        ];
    }

    /**
     * Get the usage recorded over the last given number of days.
     *
     * @return Collection<string, array<string, int|float>>
     */
    public function forDays(int $days = 7): Collection
    {
        return collect(range($days - 1, 0))
            ->mapWithKeys(function (int $ago): array {
                $date = Carbon::now()->subDays($ago)->toDateString();

                return [$date => $this->summarise($this->forDate($date))];
            });
    }

    /**
     * Summarize a day of usage with its hit rate and cost.
     *
     * @param  array<string, int>  $stats
     * @return array<string, float|int>
     */
    private function summarise(array $stats): array
    {
        $lookups = $stats['calls'] + $stats['cache_hits'];

        return [
            ...$stats,
            'hit_rate' => $lookups > 0 ? round($stats['cache_hits'] / $lookups * 100, 1) : 0.0,
            'cost' => round($stats['characters'] / 1_000_000 * $this->config->pricePerMillion(), 4),
        ];
    }

    /**
     * Flush every day of usage still within the retention window.
     */
    public function flush(): void
    {
        $store = $this->cache->store($this->config->cacheStore());

        for ($ago = 0; $ago < $this->config->statsRetentionDays(); $ago++) {
            $store->forget($this->key(Carbon::now()->subDays($ago)->toDateString()));
        }
    }

    /**
     * Get the cache key holding the usage for the given date.
     */
    private function key(string $date): string
    {
        return $this->config->cachePrefix().':stats:'.$date;
    }
}
