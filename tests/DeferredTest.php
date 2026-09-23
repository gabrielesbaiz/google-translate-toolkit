<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Jobs\WarmTranslationCacheJob;
use Illuminate\Support\Facades\Bus;

it('runs a deferred translation through the callback', function () {
    GoogleTranslateToolkit::fake();

    $captured = null;

    $result = GoogleTranslateToolkit::deferred()->translate('hello', function (Translation $translation) use (&$captured) {
        $captured = $translation->translatedText;
    });

    expect($result)->toBeNull()
        ->and($captured)->toBe('[it] hello');
});

it('warms the cache through a queued batch', function () {
    Bus::fake();

    config()->set('google-translate-toolkit.chunk.max_segments', 2);

    GoogleTranslateToolkit::warm(['a', 'b', 'c'], ['it', 'fr']);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2
        && $batch->jobs->first() instanceof WarmTranslationCacheJob);
});

it('fills the cache when the warm job runs', function () {
    config()->set('google-translate-toolkit.cache.enabled', true);

    $fake = GoogleTranslateToolkit::fake();

    (new WarmTranslationCacheJob(['hello'], ['it']))->handle(app(Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit::class));

    $fake->assertTranslatedCount(1);

    GoogleTranslateToolkit::justTranslate('hello');

    $fake->assertTranslatedCount(1);
});
