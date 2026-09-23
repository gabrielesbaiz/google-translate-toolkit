<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;

it('estimates characters, requests and cost', function () {
    $estimate = GoogleTranslateToolkit::estimate(['hello', 'world'], ['it', 'fr']);

    expect($estimate->segments)->toBe(2)
        ->and($estimate->characters)->toBe(10)
        ->and($estimate->billableCharacters)->toBe(20)
        ->and($estimate->targets)->toBe(2)
        ->and($estimate->cost)->toBe(20 / 1_000_000 * 20.0)
        ->and($estimate->formattedCost())->toContain('USD');
});

it('counts requests per chunk', function () {
    config()->set('google-translate-toolkit.chunk.max_segments', 2);

    expect(GoogleTranslateToolkit::estimate(['a', 'b', 'c'], ['it'])->requests)->toBe(2);
});

it('records usage statistics', function () {
    GoogleTranslateToolkit::fake();

    GoogleTranslateToolkit::translateBatch(['one', 'two']);
    GoogleTranslateToolkit::translateBatch(['one', 'two']);

    $today = GoogleTranslateToolkit::usage()->today();

    expect($today['calls'])->toBe(2)
        ->and($today['characters'])->toBe(12)
        ->and(GoogleTranslateToolkit::stats(1))->toHaveCount(1);
});

it('counts cache hits in the statistics', function () {
    config()->set('google-translate-toolkit.cache.enabled', true);

    GoogleTranslateToolkit::fake();

    GoogleTranslateToolkit::justTranslate('hello');
    GoogleTranslateToolkit::justTranslate('hello');

    expect(GoogleTranslateToolkit::usage()->today()['cache_hits'])->toBe(1);
});
