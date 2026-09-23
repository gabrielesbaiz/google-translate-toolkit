<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

it('translates deterministically without touching http', function () {
    $fake = GoogleTranslateToolkit::fake();

    Http::fake();

    expect(GoogleTranslateToolkit::justTranslate('hello'))->toBe('[it] hello');

    $fake->assertTranslated('hello')->assertTranslatedTo('it')->assertTranslatedCount(1);

    Http::assertNothingSent();
});

it('accepts stubbed translations', function () {
    $fake = GoogleTranslateToolkit::fake([
        'hello' => 'ciao',
        'bye' => ['fr' => 'au revoir', 'it' => 'ciao ciao'],
        'dynamic' => fn (string $text, string $target) => strtoupper($target).':'.$text,
    ]);

    expect(GoogleTranslateToolkit::justTranslate('hello'))->toBe('ciao')
        ->and(GoogleTranslateToolkit::to('fr')->text('bye'))->toBe('au revoir')
        ->and(GoogleTranslateToolkit::justTranslate('dynamic'))->toBe('IT:dynamic');

    $fake->assertNotTranslated('never sent');
});

it('records detections', function () {
    $fake = GoogleTranslateToolkit::fake()->detectAs('fr');

    expect(GoogleTranslateToolkit::detect('bonjour')->languageCode)->toBe('fr');

    $fake->assertDetected('bonjour');
});

it('asserts nothing was translated', function () {
    GoogleTranslateToolkit::fake()->assertNothingTranslated();
});
