<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

/**
 * The exact config file a 1.x application has published, with nothing else in it.
 */
beforeEach(function () {
    config()->set('google-translate-toolkit', [
        'default_source_translation' => 'en',
        'default_target_translation' => 'it',
        'api_key' => 'legacy-key',
    ]);
});

it('works with a 1.x published config and no other keys', function () {
    fakeTranslations(['Il messaggio è stato rifiutato']);

    expect(GoogleTranslateToolkit::justTranslate('The message bounced'))
        ->toBe('Il messaggio è stato rifiutato');

    Http::assertSent(fn ($request) => $request['source'] === 'en'
        && $request['target'] === 'it'
        && $request['format'] === 'text'
        && $request->hasHeader('X-goog-api-key', 'legacy-key'));
});

it('still caches, retries and protects placeholders with defaults only', function () {
    Http::fake(['*' => Http::sequence()
        ->push([], 503)
        ->push(['data' => ['translations' => [['translatedText' => 'Ciao :name']]]]),
    ]);

    expect(GoogleTranslateToolkit::justTranslate('Hello :name'))->toBe('Ciao :name');
    expect(GoogleTranslateToolkit::justTranslate('Hello :name'))->toBe('Ciao :name');

    Http::assertSentCount(2);
});
