<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

function fakeUpper(): void
{
    Http::fake(['*' => function ($request) {
        return Http::response(['data' => ['translations' => array_map(
            fn (string $text) => ['translatedText' => mb_strtoupper($text)],
            (array) $request['q'],
        )]]);
    }]);
}

it('translates selected leaves of a nested payload', function () {
    fakeUpper();

    $payload = [
        'id' => 7,
        'title' => 'hello',
        'meta' => ['slug' => 'keep-me', 'description' => 'world'],
        'items' => [['title' => 'first'], ['title' => 'second']],
    ];

    $result = GoogleTranslateToolkit::translateJson($payload, ['title', 'meta.description', 'items.*.title']);

    expect($result['title'])->toBe('HELLO')
        ->and($result['meta']['description'])->toBe('WORLD')
        ->and($result['meta']['slug'])->toBe('keep-me')
        ->and($result['items'][1]['title'])->toBe('SECOND')
        ->and($result['id'])->toBe(7);

    Http::assertSentCount(1);
});

it('accepts a json string and honours except', function () {
    fakeUpper();

    $result = GoogleTranslateToolkit::translateJson('{"a":"one","b":"two"}', ['*'], ['b']);

    expect($result)->toBe(['a' => 'ONE', 'b' => 'two']);
});

it('streams large sets lazily in chunks', function () {
    config()->set('google-translate-toolkit.chunk.max_segments', 2);

    fakeUpper();

    $texts = ['a', 'b', 'c', 'd', 'e'];

    $translations = GoogleTranslateToolkit::lazy($texts, chunkSize: 2);

    expect($translations)->toBeInstanceOf(Illuminate\Support\LazyCollection::class);

    $collected = $translations->map(fn (Translation $translation) => $translation->translatedText)->all();

    expect($collected)->toBe(['A', 'B', 'C', 'D', 'E']);

    Http::assertSentCount(3);
});

it('refuses to stream into several languages at once', function () {
    GoogleTranslateToolkit::to(['it', 'fr'])->lazy(['a']);
})->throws(Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\GoogleTranslateException::class);
