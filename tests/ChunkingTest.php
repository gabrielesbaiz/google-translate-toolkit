<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

it('splits large batches into several requests', function () {
    config()->set('google-translate-toolkit.chunk.max_segments', 2);

    Http::fake(['*' => Http::response([
        'data' => ['translations' => [['translatedText' => 'x'], ['translatedText' => 'y']]],
    ])]);

    GoogleTranslateToolkit::translateBatch(['a', 'b', 'c', 'd']);

    Http::assertSentCount(2);
});

it('splits on the character budget too', function () {
    config()->set('google-translate-toolkit.chunk.max_characters', 5);

    Http::fake(['*' => Http::response([
        'data' => ['translations' => [['translatedText' => 'x']]],
    ])]);

    GoogleTranslateToolkit::translateBatch(['aaaa', 'bbbb', 'cccc']);

    Http::assertSentCount(3);
});

it('keeps the original order across chunks', function () {
    config()->set('google-translate-toolkit.chunk.max_segments', 1);

    $responses = collect(['uno', 'due', 'tre'])
        ->map(fn (string $text) => Http::response(['data' => ['translations' => [['translatedText' => $text]]]]))
        ->all();

    Http::fake(['*' => Http::sequence($responses)]);

    expect(GoogleTranslateToolkit::translateBatch(['one', 'two', 'three'])->texts()->all())
        ->toBe(['uno', 'due', 'tre']);
});
