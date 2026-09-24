<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config()->set('google-translate-toolkit.cache.enabled', true));

it('serves a repeated translation from cache', function () {
    fakeTranslations(['ciao']);

    expect(GoogleTranslateToolkit::justTranslate('hi'))->toBe('ciao');
    expect(GoogleTranslateToolkit::justTranslate('hi'))->toBe('ciao');

    Http::assertSentCount(1);
});

it('only requests the uncached segments of a batch', function () {
    fakeTranslations(['uno', 'due']);

    GoogleTranslateToolkit::translateBatch(['one', 'two']);

    fakeTranslations(['tre']);

    $translations = GoogleTranslateToolkit::translateBatch(['one', 'two', 'three']);

    expect($translations->texts()->all())->toBe(['uno', 'due', 'tre']);

    Http::assertSent(fn ($request) => $request['q'] === ['three']);
    Http::assertSentCount(1);
});

it('marks cached translations', function () {
    fakeTranslations(['ciao']);

    expect(GoogleTranslateToolkit::translate('hi')->cached)->toBeFalse();
    expect(GoogleTranslateToolkit::translate('hi')->cached)->toBeTrue();
});

it('bypasses the cache on demand', function () {
    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::justTranslate('hi');
    GoogleTranslateToolkit::withoutCache()->text('hi');

    Http::assertSentCount(2);
});

it('invalidates everything it cached when flushed', function () {
    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::justTranslate('hi');
    GoogleTranslateToolkit::flushCache();
    GoogleTranslateToolkit::justTranslate('hi');

    Http::assertSentCount(2);
});

it('never caches text handed back by the fallback', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    expect(GoogleTranslateToolkit::onFailUseSource()->text('hi'))->toBe('hi');

    Http::assertSentCount(1);

    fakeTranslations(['ciao']);

    // The failure must not have poisoned the cache with the source text.
    expect(GoogleTranslateToolkit::justTranslate('hi'))->toBe('ciao');
});
