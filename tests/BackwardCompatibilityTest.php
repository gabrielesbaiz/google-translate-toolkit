<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

it('keeps the 1.x justTranslate signature', function () {
    fakeTranslations(['ciao']);

    expect(GoogleTranslateToolkit::justTranslate('hi', 'en', 'it'))->toBe('ciao');

    Http::assertSent(fn ($request) => $request['source'] === 'en' && $request['target'] === 'it');
});

it('still exposes the 1.x array keys on a translation', function () {
    fakeTranslations(['ciao'], detected: 'en');

    $translation = GoogleTranslateToolkit::translate('hi');

    expect($translation['source_text'])->toBe('hi')
        ->and($translation['translated_text'])->toBe('ciao')
        ->and($translation['source_language_code'])->toBe('en')
        ->and($translation['translated_language_code'])->toBe('it')
        ->and($translation->toArray())->toHaveKeys(['source_text', 'translated_text', 'source_language_code', 'translated_language_code']);
});

it('still exposes the 1.x array keys on a detection', function () {
    fakeTranslations([], detected: 'fr');

    $detection = GoogleTranslateToolkit::detectLanguage('bonjour');

    expect($detection['text'])->toBe('bonjour')
        ->and($detection['language_code'])->toBe('fr')
        ->and($detection['confidence'])->toBe(0.99);
});

it('keeps detectLanguageBatch working', function () {
    Http::fake(['*language/translate/v2/detect*' => Http::response(['data' => ['detections' => [
        [['language' => 'en', 'confidence' => 0.9, 'isReliable' => true]],
        [['language' => 'it', 'confidence' => 0.8, 'isReliable' => true]],
    ]]])]);

    $detections = GoogleTranslateToolkit::detectLanguageBatch(['hello', 'ciao']);

    expect($detections->pluck('languageCode')->all())->toBe(['en', 'it']);
});

it('keeps translateBatch and getAvailableTranslationsFor working', function () {
    fakeTranslations(['uno', 'due']);

    expect(GoogleTranslateToolkit::translateBatch(['one', 'two'], 'en', 'it')->texts()->all())->toBe(['uno', 'due'])
        ->and(GoogleTranslateToolkit::getAvailableTranslationsFor('it')->get('it'))->toBe('Italiano');
});

it('keeps unlessLanguageIs from translating text already in the target language', function () {
    fakeTranslations(['ciao'], detected: 'it');

    $result = GoogleTranslateToolkit::unlessLanguageIs('it', 'ciao');

    expect($result->translatedText)->toBe('ciao')
        ->and($result->isUnchanged())->toBeTrue();

    Http::assertSentCount(1);
});

it('keeps sanitizeLanguageCode working, zh-TW included', function () {
    expect(GoogleTranslateToolkit::sanitizeLanguageCode('IT'))->toBe('it')
        ->and(GoogleTranslateToolkit::sanitizeLanguageCode('zh-tw'))->toBe('zh-TW');
});

it('reads the 1.x config keys when only those are published', function () {
    config()->set('google-translate-toolkit.default_target', null);
    config()->set('google-translate-toolkit.default_source', null);
    config()->set('google-translate-toolkit.default_target_translation', 'fr');
    config()->set('google-translate-toolkit.default_source_translation', 'en');

    fakeTranslations(['bonjour']);

    GoogleTranslateToolkit::justTranslate('hi');

    Http::assertSent(fn ($request) => $request['target'] === 'fr' && $request['source'] === 'en');
});
