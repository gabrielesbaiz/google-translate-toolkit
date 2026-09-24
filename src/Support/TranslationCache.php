<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Stores translated segments so repeated translations never reach the API.
 */
final class TranslationCache
{
    /**
     * Create a new translation cache instance.
     */
    public function __construct(
        private readonly Config $config,
        private readonly CacheFactory $cache,
    ) {}

    /**
     * Determine if translations should be cached.
     */
    public function enabled(): bool
    {
        return $this->config->cacheEnabled();
    }

    /**
     * Get the cache store translations are written to.
     */
    public function store(): Repository
    {
        return $this->cache->store($this->config->cacheStore());
    }

    /**
     * Get the cache key for the given segment.
     */
    public function key(string $text, ?string $source, string $target, TextFormat $format): string
    {
        return sprintf(
            '%s:v%d:%s',
            $this->config->cachePrefix(),
            $this->version(),
            sha1(implode('|', [$source ?? 'auto', $target, $format->value, $text])),
        );
    }

    /**
     * Get the cached translation of the given segment.
     */
    public function get(string $text, ?string $source, string $target, TextFormat $format): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $cached = $this->store()->get($this->key($text, $source, $target, $format));

        return is_string($cached) ? $cached : null;
    }

    /**
     * Cache the translation of the given segment.
     */
    public function put(string $text, string $translated, ?string $source, string $target, TextFormat $format, ?int $ttl = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->store()->put(
            $this->key($text, $source, $target, $format),
            $translated,
            $ttl ?? $this->config->cacheTtl(),
        );
    }

    /**
     * Split a batch into cache hits and misses.
     *
     * The original indexes are kept on both sides so the caller can merge the
     * API results back into the batch in order.
     *
     * @param  array<int, string>  $texts
     * @return array{hits: array<int, string>, misses: array<int, string>}
     */
    public function partition(array $texts, ?string $source, string $target, TextFormat $format): array
    {
        if (! $this->enabled()) {
            return ['hits' => [], 'misses' => $texts];
        }

        $hits = [];
        $misses = [];

        foreach ($texts as $index => $text) {
            $cached = $this->get($text, $source, $target, $format);

            if ($cached === null) {
                $misses[$index] = $text;

                continue;
            }

            $hits[$index] = $cached;
        }

        return ['hits' => $hits, 'misses' => $misses];
    }

    /**
     * Get the cached value for the key, resolving it through the callback when it is missing.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= $this->config->cacheTtl();
        $key = $this->config->cachePrefix().':v'.$this->version().':'.$key;

        if (! $this->enabled()) {
            return $callback();
        }

        $store = $this->store();

        if (method_exists($store, 'flexible')) {
            return $store->flexible($key, [(int) max(1, $ttl / 2), $ttl], $callback);
        }

        return $store->remember($key, $ttl, $callback);
    }

    /**
     * Flush every translation this package cached, leaving the rest of the store alone.
     */
    public function flush(): int
    {
        $version = $this->version() + 1;

        $this->cache->store($this->config->cacheStore())->forever($this->versionKey(), $version);

        return $version;
    }

    /**
     * Get the current version of the cache namespace.
     */
    private function version(): int
    {
        return (int) $this->cache->store($this->config->cacheStore())->get($this->versionKey(), 1);
    }

    /**
     * Get the cache key holding the namespace version.
     */
    private function versionKey(): string
    {
        return $this->config->cachePrefix().':version';
    }
}
