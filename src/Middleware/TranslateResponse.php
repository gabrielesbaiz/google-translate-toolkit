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
 * Opt-in: translates configured JSON fields to the language negotiated from
 * Accept-Language. Every uncached field costs money - enable deliberately.
 */
class TranslateResponse
{
    public function __construct(
        private readonly GoogleTranslateToolkit $toolkit,
        private readonly Config $config,
    ) {}

    /** @param Closure(Request): Response $next */
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
