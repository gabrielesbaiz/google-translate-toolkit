<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Data\TranslationCollection;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\Http;

it('translates a single string', function () {
    fakeTranslations(['Ciao mondo'], detected: 'en');

    $translation = GoogleTranslateToolkit::translate('Hello world');

    expect($translation)->toBeInstanceOf(Translation::class)
        ->and($translation->translatedText)->toBe('Ciao mondo')
        ->and($translation->targetLanguage)->toBe('it')
        ->and($translation->sourceLanguage)->toBe('en')
        ->and($translation->detected)->toBeTrue()
        ->and((string) $translation)->toBe('Ciao mondo');
});

it('returns just the string with justTranslate', function () {
    fakeTranslations(['Ciao mondo']);

    expect(GoogleTranslateToolkit::justTranslate('Hello world'))->toBe('Ciao mondo');
});

it('decodes html entities for plain text', function () {
    fakeTranslations(['L&#39;italiano &amp; il resto']);

    expect(GoogleTranslateToolkit::justTranslate('Italian and the rest'))->toBe("L'italiano & il resto");
});

it('keeps entities untouched in html mode', function () {
    fakeTranslations(['<p>L&#39;italiano</p>']);

    expect(GoogleTranslateToolkit::asHtml()->text('<p>Italian</p>'))->toBe('<p>L&#39;italiano</p>');
});

it('translates a batch in one request', function () {
    fakeTranslations(['uno', 'due', 'tre']);

    $translations = GoogleTranslateToolkit::translateBatch(['one', 'two', 'three']);

    expect($translations)->toBeInstanceOf(TranslationCollection::class)
        ->and($translations->texts()->all())->toBe(['uno', 'due', 'tre']);

    Http::assertSentCount(1);
});

it('omits the source language so google auto-detects', function () {
    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::justTranslate('hi');

    Http::assertSent(fn ($request) => ! array_key_exists('source', $request->data()));
});

it('sends the source language when one is given', function () {
    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::from(Language::English)->to('it')->text('hi');

    Http::assertSent(fn ($request) => $request['source'] === 'en' && $request['target'] === 'it');
});

it('authenticates with a header instead of the query string', function () {
    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::justTranslate('hi');

    Http::assertSent(fn ($request) => $request->hasHeader('X-goog-api-key', 'test-key')
        && ! str_contains($request->url(), 'test-key'));
});

it('rejects unknown language codes in strict mode', function () {
    fakeTranslations(['ciao']);

    GoogleTranslateToolkit::to('klingon')->text('hi');
})->throws(Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\UnsupportedLanguageException::class);

it('normalises regional codes', function () {
    fakeTranslations(['你好']);

    GoogleTranslateToolkit::to('zh-tw')->text('hello');

    Http::assertSent(fn ($request) => $request['target'] === 'zh-TW');
});
