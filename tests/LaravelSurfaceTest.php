<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Data\Translation;
use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslate;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Rules\LanguageRule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

function fakeEchoWithTarget(): void
{
    Http::fake(['*' => function ($request) {
        return Http::response(['data' => ['translations' => array_map(
            fn (string $text) => ['translatedText' => '['.$request['target'].'] '.$text],
            (array) $request['q'],
        )]]);
    }]);
}

it('exposes a Str macro', function () {
    fakeEchoWithTarget();

    expect(Str::translate('hi'))->toBe('[it] hi')
        ->and(Str::translate('hi', 'fr'))->toBe('[fr] hi');
});

it('exposes Stringable macros', function () {
    fakeEchoWithTarget();

    expect((string) str('hi')->translate())->toBe('[it] hi')
        ->and((string) str('hi')->translateTo('de')->upper())->toBe('[DE] HI');
});

it('exposes a Collection macro', function () {
    fakeEchoWithTarget();

    expect(collect(['a', 'b'])->translate('fr')->texts()->all())->toBe(['[fr] a', '[fr] b']);
});

it('exposes a helper function', function () {
    fakeEchoWithTarget();

    expect(google_translate('hi', 'es'))->toBeInstanceOf(Translation::class)
        ->and(google_translate('hi', 'es')->translatedText)->toBe('[es] hi')
        ->and(google_translate())->toBeInstanceOf(Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit::class);
});

it('compiles the translate directive with the target in the right place', function () {
    fakeEchoWithTarget();

    $compiled = Blade::compileString("@translate('hello', 'fr')");

    expect($compiled)->toContain('blade(');

    $rendered = Blade::render("@translate('hello', 'fr')");

    expect($rendered)->toBe('[fr] hello');
});

it('renders html through the translateHtml directive', function () {
    fakeEchoWithTarget();

    expect(Blade::render("@translateHtml('<b>hi</b>', 'fr')"))->toBe('[fr] <b>hi</b>');
});

it('keeps the source text in blade when the api fails', function () {
    Http::fake(['*' => Http::response([], 500)]);

    expect(Blade::render("@translate('hello')"))->toBe('hello');
});

it('validates language codes', function () {
    expect(Validator::make(['locale' => 'it'], ['locale' => Rule::language()])->passes())->toBeTrue()
        ->and(Validator::make(['locale' => 'klingon'], ['locale' => new LanguageRule])->passes())->toBeFalse()
        ->and(Validator::make(['locale' => 'fr'], ['locale' => Rule::language(['it', 'en'])])->passes())->toBeFalse();
});

it('offers both facade names', function () {
    fakeEchoWithTarget();

    expect(GoogleTranslate::justTranslate('hi'))->toBe(GoogleTranslateToolkit::justTranslate('hi'));
});

it('lists languages offline and from the api', function () {
    fakeTranslations([]);

    expect(GoogleTranslateToolkit::supportedLanguages())->toHaveCount(count(Language::cases()))
        ->and(GoogleTranslateToolkit::languages()->all())->toBe(['en' => 'Inglese', 'it' => 'Italiano']);
});
