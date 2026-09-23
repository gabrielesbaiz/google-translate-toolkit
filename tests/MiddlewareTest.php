<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Middleware\TranslateResponse;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(TranslateResponse::class)->get('/posts', fn () => response()->json([
        'data' => [['title' => 'hello', 'slug' => 'hello']],
    ]));
});

it('leaves responses alone while disabled', function () {
    GoogleTranslateToolkit::fake();

    $this->withHeader('Accept-Language', 'it')->get('/posts')
        ->assertJsonPath('data.0.title', 'hello');
});

it('translates the configured fields to the negotiated language', function () {
    config()->set('google-translate-toolkit.middleware.enabled', true);
    config()->set('google-translate-toolkit.middleware.fields', ['data.*.title']);

    GoogleTranslateToolkit::fake();

    $this->withHeader('Accept-Language', 'it')->get('/posts')
        ->assertJsonPath('data.0.title', '[it] hello')
        ->assertJsonPath('data.0.slug', 'hello');
});

it('skips translating when the visitor already speaks the app language', function () {
    config()->set('google-translate-toolkit.middleware.enabled', true);
    config()->set('google-translate-toolkit.middleware.fields', ['data.*.title']);

    $fake = GoogleTranslateToolkit::fake();

    $this->withHeader('Accept-Language', 'en')->get('/posts')->assertJsonPath('data.0.title', 'hello');

    $fake->assertNothingTranslated();
});
