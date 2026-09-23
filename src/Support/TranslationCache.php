<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Cached segments never reach the API, which is the single biggest cost saver here.
 */
final class TranslationCache
{
    public function __construct(
        private readonly Config $config,
        private readonly CacheFactory $cache,
    ) {}

    public function enabled(): bool
    {
        return $this->config->cacheEnabled();
    }

    public function store(): Repository
    {
        return $this->cache->store($this->config->cacheStore());
    }

    public function key(string $text, ?string $source, string $target, TextFormat $format): string
    {
        return sprintf(
            '%s:v%d:%s',
            $this->config->cachePrefix(),
            $this->version(),
            sha1(implode('|', [$source ?? 'auto', $target, $format->value, $text])),
        );
    }

    public function get(string $text, ?string $source, string $target, TextFormat $format): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $cached = $this->store()->get($this->key($text, $source, $target, $format));

        return is_string($cached) ? $cached : null;
    }

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
     * Split a batch into cache hits and misses, keeping the original indexes so
     * the caller can merge the API results back in order.
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
     * Stale-while-revalidate when the store supports it, plain remember otherwise.
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
     * Invalidate everything this package cached without touching the rest of the store.
     */
    public function flush(): int
    {
        $version = $this->version() + 1;

        $this->cache->store($this->config->cacheStore())->forever($this->versionKey(), $version);

        return $version;
    }

    private function version(): int
    {
        return (int) $this->cache->store($this->config->cacheStore())->get($this->versionKey(), 1);
    }

    private function versionKey(): string
    {
        return $this->config->cachePrefix().':version';
    }
}
