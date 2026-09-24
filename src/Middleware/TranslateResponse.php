<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Middleware;

use Closure;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Translates the configured JSON response fields into the language negotiated
 * from the Accept-Language header. Every uncached field costs money, so the
 * middleware stays disabled until it is enabled in the configuration.
 */
class TranslateResponse
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly GoogleTranslateToolkit $toolkit,
        private readonly Config $config,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string ...$fields): Response
    {
        $response = $next($request);

        if (! $this->config->middlewareEnabled() || ! $response instanceof JsonResponse || ! $response->isSuccessful()) {
            return $response;
        }

        $target = $this->negotiate($request);

        if ($target === null) {
            return $response;
        }

        $only = array_values(array_filter($fields)) ?: $this->config->middlewareFields();

        if ($only === []) {
            return $response;
        }

        $data = $response->getData(true);

        if (! is_array($data)) {
            return $response;
        }

        return $response->setData($this->toolkit->to($target)->onFailUseSource()->json($data, $only));
    }

    /**
     * Resolve the target language from the request, if it differs from the application locale.
     */
    private function negotiate(Request $request): ?string
    {
        $locales = $this->config->middlewareLocales() ?: Language::codes();

        $preferred = $request->getPreferredLanguage($locales);

        if ($preferred === null) {
            return null;
        }

        $target = Language::normalize($preferred);

        return $target === Language::normalize(app()->getLocale()) ? null : $target;
    }
}
