<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

it('fans a single string out to several languages', function () {
    Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => 'traduzione']]]])]);

    $results = GoogleTranslateToolkit::to(['it', 'fr', 'de'])->translate('hello');

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results->keys()->all())->toBe(['it', 'fr', 'de'])
        ->and($results->get('fr'))->toBeInstanceOf(Translation::class)
        ->and($results->get('fr')->targetLanguage)->toBe('fr');

    Http::assertSentCount(3);
});

it('returns a collection of collections for batches', function () {
    Http::fake(['*' => Http::response(['data' => ['translations' => [
        ['translatedText' => 'uno'],
        ['translatedText' => 'due'],
    ]]])]);

    $results = GoogleTranslateToolkit::to(['it', 'es'])->many(['one', 'two']);

    expect($results->keys()->all())->toBe(['it', 'es'])
        ->and($results->get('it'))->toBeInstanceOf(TranslationCollection::class)
        ->and($results->get('es')->texts()->all())->toBe(['uno', 'due']);
});

it('deduplicates repeated targets', function () {
    Http::fake(['*' => Http::response(['data' => ['translations' => [['translatedText' => 'ciao']]]])]);

    GoogleTranslateToolkit::to(['it', 'it', 'IT'])->translate('hi');

    Http::assertSentCount(1);
});
