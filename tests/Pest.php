<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Tests\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in(__DIR__);

/**
 * Fake a Google v2 translate response for the given translations, in order.
 *
 * @param  array<int, string>  $translations
 */
function fakeTranslations(array $translations, ?string $detected = null): void
{
    // Http::fake() merges its stubs, so here we will reset them first and let a
    // later call within the same test replace the responses of an earlier one.
    Closure::bind(function (): void {
        $this->stubCallbacks = collect();
    }, app(Factory::class), Factory::class)();

    Http::fake([
        '*language/translate/v2/detect*' => Http::response([
            'data' => ['detections' => [[['language' => $detected ?? 'en', 'confidence' => 0.99, 'isReliable' => true]]]],
        ]),
        '*language/translate/v2/languages*' => Http::response([
            'data' => ['languages' => [['language' => 'en', 'name' => 'Inglese'], ['language' => 'it', 'name' => 'Italiano']]],
        ]),
        '*language/translate/v2*' => Http::response([
            'data' => [
                'translations' => array_map(
                    fn (string $text) => array_filter([
                        'translatedText' => $text,
                        'detectedSourceLanguage' => $detected,
                    ], fn ($value) => $value !== null),
                    $translations,
                ),
            ],
        ]),
    ]);
}
