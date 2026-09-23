<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Drivers;

use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\TextFormat;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\MissingApiKeyException;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\TranslationFailedException;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Chunker;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Config;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Throttle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Cloud Translation v2 over Laravel's HTTP client: no SDK, no gRPC, fully fakeable.
 */
final class GoogleTranslateApi implements Translator
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
        private readonly Chunker $chunker,
        private readonly Throttle $throttle,
    ) {}

    /**
     * @param  array<array-key, string>  $texts
     * @return array<array-key, array{text: string, detectedSourceLanguage: string|null}>
     */
    public function translate(array $texts, ?string $source, string $target, TextFormat $format): array
    {
        return $this->translateMany($texts, $source, [$target], $format)[$target] ?? [];
    }

    /**
     * @param  array<array-key, string>  $texts
     * @param  array<int, string>  $targets
     * @return array<string, array<array-key, array{text: string, detectedSourceLanguage: string|null}>>
     */
    public function translateMany(array $texts, ?string $source, array $targets, TextFormat $format): array
    {
        $targets = array_values(array_unique($targets));

        if ($texts === [] || $targets === []) {
            return array_fill_keys($targets, []);
        }

        $jobs = [];

        foreach ($targets as $target) {
            foreach ($this->chunker->chunk($texts) as $chunkIndex => $chunk) {
                $jobs[$target.'|'.$chunkIndex] = ['target' => $target, 'chunk' => $chunk];
            }
        }

        $this->throttle->hit(count($jobs));

        $responses = $this->dispatch($jobs, fn (PendingRequest|Pool $request, array $job) => $request->post($this->config->baseUrl(), array_filter([
            'q' => array_values($job['chunk']),
            'source' => $source,
            'target' => $job['target'],
            'format' => $format->value,
        ], fn ($value) => $value !== null)));

        $results = array_fill_keys($targets, []);

        foreach ($jobs as $name => $job) {
            $translations = $this->payload($responses[$name], 'translations');
            $keys = array_keys($job['chunk']);

            foreach (array_values($translations) as $position => $translation) {
                if (! array_key_exists($position, $keys) || ! is_array($translation)) {
                    throw TranslationFailedException::malformedResponse();
                }

                $results[$job['target']][$keys[$position]] = [
                    'text' => $this->decode((string) ($translation['translatedText'] ?? ''), $format),
                    'detectedSourceLanguage' => $translation['detectedSourceLanguage'] ?? null,
                ];
            }
        }

        foreach ($targets as $target) {
            ksort($results[$target]);
        }

        return $results;
    }

    /**
     * @param  array<array-key, string>  $texts
     * @return array<array-key, array{language: string, confidence: float, reliable: bool}>
     */
    public function detect(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $jobs = [];

        foreach ($this->chunker->chunk($texts) as $chunkIndex => $chunk) {
            $jobs['chunk-'.$chunkIndex] = ['target' => '', 'chunk' => $chunk];
        }

        $this->throttle->hit(count($jobs));

        $responses = $this->dispatch($jobs, fn (PendingRequest|Pool $request, array $job) => $request->post(
            $this->config->baseUrl().'/detect',
            ['q' => array_values($job['chunk'])],
        ));

        $results = [];

        foreach ($jobs as $name => $job) {
            $detections = $this->payload($responses[$name], 'detections');
            $keys = array_keys($job['chunk']);

            foreach (array_values($detections) as $position => $detection) {
                // Each entry is a list of candidates, best first.
                $candidates = is_array($detection) ? $detection : [];
                $best = is_array($candidates[0] ?? null) ? $candidates[0] : $candidates;

                $results[$keys[$position] ?? $position] = [
                    'language' => (string) ($best['language'] ?? 'und'),
                    'confidence' => (float) ($best['confidence'] ?? 0.0),
                    'reliable' => (bool) ($best['isReliable'] ?? false),
                ];
            }
        }

        ksort($results);

        return $results;
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function languages(string $displayLanguage): array
    {
        $this->throttle->hit();

        $response = $this->request()->get($this->config->baseUrl().'/languages', [
            'target' => $displayLanguage,
        ]);

        return array_values(array_map(
            fn (mixed $language): array => [
                'code' => (string) (is_array($language) ? ($language['language'] ?? '') : ''),
                'name' => (string) (is_array($language) ? ($language['name'] ?? '') : ''),
            ],
            $this->payload($response, 'languages'),
        ));
    }

    /**
     * Send one job directly, or several concurrently through a bounded pool.
     *
     * @param  array<string, array{target: string, chunk: array<array-key, string>}>  $jobs
     * @param  callable(PendingRequest|Pool, array{target: string, chunk: array<array-key, string>}): mixed  $callback
     * @return array<string, Response>
     */
    private function dispatch(array $jobs, callable $callback): array
    {
        if (count($jobs) === 1) {
            $name = array_key_first($jobs);

            /** @var Response $response */
            $response = $callback($this->request(), $jobs[$name]);

            return [$name => $response];
        }

        $responses = [];

        foreach (array_chunk($jobs, $this->config->concurrency(), preserve_keys: true) as $batch) {
            $pooled = $this->http->pool(function (Pool $pool) use ($batch, $callback): array {
                return array_map(
                    fn (array $job, string $name) => $callback($this->configure($pool->as($name)), $job),
                    array_values($batch),
                    array_keys($batch),
                );
            });

            foreach ($batch as $name => $job) {
                $result = $pooled[$name] ?? null;

                if ($result instanceof Throwable) {
                    throw TranslationFailedException::fromThrowable($result);
                }

                if (! $result instanceof Response) {
                    throw TranslationFailedException::malformedResponse();
                }

                $responses[$name] = $result;
            }
        }

        return $responses;
    }

    private function request(): PendingRequest
    {
        return $this->configure($this->http->asJson());
    }

    /**
     * @template TRequest of PendingRequest|Pool
     *
     * @param  TRequest  $request
     * @return TRequest
     */
    private function configure(mixed $request): mixed
    {
        $retry = $this->config->retry();

        return $request
            ->withHeaders([
                'X-goog-api-key' => $this->apiKey(),
                'Accept' => 'application/json',
            ])
            ->asJson()
            ->timeout($this->config->timeout())
            ->connectTimeout($this->config->connectTimeout())
            ->retry(
                $retry['times'],
                $retry['backoff']
                    ? fn (int $attempt): int => $retry['sleep'] * (2 ** ($attempt - 1))
                    : $retry['sleep'],
                fn (Throwable $exception, PendingRequest $request): bool => $this->shouldRetry($exception),
                throw: false,
            );
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $status = $exception instanceof RequestException
            ? $exception->response->status()
            : (int) $exception->getCode();

        return $status === 0 || $status === 429 || $status >= 500;
    }

    /**
     * @return array<int, mixed>
     */
    private function payload(Response $response, string $key): array
    {
        if ($response->failed()) {
            throw TranslationFailedException::fromResponse($response);
        }

        $data = $response->json('data.'.$key);

        if (! is_array($data)) {
            throw TranslationFailedException::malformedResponse();
        }

        return $data;
    }

    private function decode(string $text, TextFormat $format): string
    {
        // Google returns HTML entities even for plain text payloads.
        return $format->isHtml() ? $text : html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function apiKey(): string
    {
        return $this->config->apiKey() ?? throw MissingApiKeyException::make();
    }
}
