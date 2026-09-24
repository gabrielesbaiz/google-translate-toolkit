<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Events\TranslationFailed;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\MissingApiKeyException;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\RateLimitExceededException;
use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\TranslationFailedException;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

it('turns an api error into a typed exception', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'API key not valid']], 403)]);

    GoogleTranslateToolkit::justTranslate('hi');
})->throws(TranslationFailedException::class, 'API key not valid');

it('falls back to the source text when configured', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    expect(GoogleTranslateToolkit::onFailUseSource()->text('hi'))->toBe('hi');
});

it('fires an event when a translation fails', function () {
    Event::fake();

    Http::fake(['*' => Http::response([], 500)]);

    GoogleTranslateToolkit::onFailUseSource()->text('hi');

    Event::assertDispatched(TranslationFailed::class, fn (TranslationFailed $event) => $event->recovered === true && $event->target === 'it');
});

it('retries transient failures', function () {
    config()->set('google-translate-toolkit.http.retry.times', 3);
    config()->set('google-translate-toolkit.http.retry.sleep', 0);

    Http::fake(['*' => Http::sequence()
        ->push([], 503)
        ->push(['data' => ['translations' => [['translatedText' => 'ciao']]]]),
    ]);

    expect(GoogleTranslateToolkit::justTranslate('hi'))->toBe('ciao');

    Http::assertSentCount(2);
});

it('does not retry a client error', function () {
    config()->set('google-translate-toolkit.http.retry.times', 3);
    config()->set('google-translate-toolkit.http.retry.sleep', 0);

    Http::fake(['*' => Http::response(['error' => ['message' => 'bad request']], 400)]);

    try {
        GoogleTranslateToolkit::justTranslate('hi');
    } catch (TranslationFailedException) {
        // The exception is expected here. Only the request count matters.
    }

    Http::assertSentCount(1);
});

it('requires an api key', function () {
    config()->set('google-translate-toolkit.api_key', null);

    Http::fake();

    GoogleTranslateToolkit::justTranslate('hi');
})->throws(MissingApiKeyException::class);

it('enforces the rate limit before calling the api', function () {
    config()->set('google-translate-toolkit.rate_limit.enabled', true);
    config()->set('google-translate-toolkit.rate_limit.max_per_minute', 1);

    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::justTranslate('one');
    GoogleTranslateToolkit::justTranslate('two');
})->throws(RateLimitExceededException::class);

it('stops before blowing the daily budget', function () {
    config()->set('google-translate-toolkit.budget.max_characters_per_day', 5);

    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::justTranslate('a string far longer than five characters');
})->throws(Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\BudgetExceededException::class);
