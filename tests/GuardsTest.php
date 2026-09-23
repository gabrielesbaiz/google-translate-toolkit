<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

/**
 * Emulate Google mangling everything it is given: the sentinels must still come back.
 */
function fakeEcho(callable $mutator): void
{
    Http::fake(['*' => function ($request) use ($mutator) {
        return Http::response(['data' => ['translations' => array_map(
            fn (string $text) => ['translatedText' => $mutator($text)],
            (array) $request['q'],
        )]]);
    }]);
}

it('protects laravel placeholders', function () {
    fakeEcho(fn (string $text) => str_replace('Welcome back', 'Bentornato', $text));

    $result = GoogleTranslateToolkit::justTranslate('Welcome back, :name!');

    expect($result)->toBe('Bentornato, :name!');
});

it('protects curly placeholders, urls and emails', function () {
    fakeEcho(fn (string $text) => strtoupper($text));

    $result = GoogleTranslateToolkit::justTranslate('see {count} at https://novias.it or mail me@novias.it');

    expect($result)->toContain('{count}')
        ->and($result)->toContain('https://novias.it')
        ->and($result)->toContain('me@novias.it');
});

it('never sends the raw placeholder to the api', function () {
    fakeEcho(fn (string $text) => $text);

    GoogleTranslateToolkit::justTranslate('Hello :name');

    Http::assertSent(fn ($request) => ! str_contains($request['q'][0], ':name'));
});

it('can be told to skip placeholder protection', function () {
    fakeEcho(fn (string $text) => $text);

    GoogleTranslateToolkit::query()->withoutPreserving()->text('Hello :name');

    Http::assertSent(fn ($request) => str_contains($request['q'][0], ':name'));
});

it('keeps glossary terms untranslated', function () {
    config()->set('google-translate-toolkit.glossary.protect', ['Novias']);

    fakeEcho(fn (string $text) => str_replace(['Novias', 'sent'], ['Sposa', 'inviato'], $text));

    expect(GoogleTranslateToolkit::justTranslate('Novias sent'))->toBe('Novias inviato');
});

it('applies forced glossary wording after translating', function () {
    config()->set('google-translate-toolkit.glossary.overrides', ['it' => ['rimbalzo' => 'rifiuto']]);

    fakeEcho(fn (string $text) => 'Rimbalzo permanente');

    expect(GoogleTranslateToolkit::justTranslate('Permanent bounce'))->toBe('Rifiuto permanente');
});

it('scores a round trip', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['data' => ['detections' => [[['language' => 'en', 'confidence' => 1, 'isReliable' => true]]]]])
        ->push(['data' => ['translations' => [['translatedText' => 'Il messaggio è stato rifiutato']]]])
        ->push(['data' => ['translations' => [['translatedText' => 'The message was rejected']]]]),
    ]);

    $result = GoogleTranslateToolkit::roundTrip('The message was rejected', 'it');

    expect($result->score)->toBe(1.0)
        ->and($result->isSuspicious())->toBeFalse()
        ->and($result->translated)->toBe('Il messaggio è stato rifiutato');
});
