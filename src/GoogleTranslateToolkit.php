<?php

namespace Gabrielesbaiz\GoogleTranslateToolkit;

use Exception;
use InvalidArgumentException;
use Gabrielesbaiz\GoogleTranslateToolkit\Traits\SupportedLanguages;
use Gabrielesbaiz\GoogleTranslateToolkit\Http\GoogleTranslateClient;

class GoogleTranslateToolkit
{
    use SupportedLanguages;

    private GoogleTranslateClient $translateClient;

    public function __construct(GoogleTranslateClient $client)
    {
        $this->translateClient = $client;
    }

    public function detectLanguage(string|array $input): array
    {
        if (is_array($input)) {
            return $this->detectLanguageBatch($input);
        }

        $this->validateInput($input);

        $response = $this->translateClient->detectLanguage($input);

        return [
            'text' => $input,
            'language_code' => $response['languageCode'],
            'confidence' => $response['confidence'],
        ];
    }

    public function detectLanguageBatch(array $input): array
    {
        $this->validateInput($input);

        $responses = $this->translateClient->detectLanguageBatch($input);

        return array_map(fn ($response) => [
            'text' => $response['input'],
            'language_code' => $response['languageCode'],
            'confidence' => $response['confidence'],
        ], $responses);
    }

    public function translate(string|array $input, ?string $from = null, ?string $to = null, string $format = 'text'): array
    {
        $this->validateInput($input);

        $translateFrom = $this->sanitizeLanguageCode($from ?? config('google-translate-toolkit.default_source_translation'));

        $translateTo = $this->sanitizeLanguageCode($to ?? config('google-translate-toolkit.default_target_translation'));

        if (is_array($input)) {
            return $this->translateBatch($input, $translateFrom, $translateTo, $format);
        }

        $response = $this->translateClient->translate($input, $translateFrom, $translateTo, $format);

        return [
            'source_text' => $input,
            'source_language_code' => $translateFrom,
            'translated_text' => $response['text'],
            'translated_language_code' => $translateTo,
        ];
    }

    public function justTranslate(string $input, ?string $from = null, ?string $to = null): string
    {
        $this->validateInput($input);

        $translateFrom = $this->sanitizeLanguageCode($from ?? config('google-translate-toolkit.default_source_translation'));

        $translateTo = $this->sanitizeLanguageCode($to ?? config('google-translate-toolkit.default_target_translation'));

        return $this->translateClient->translate($input, $translateFrom, $translateTo)['text'];
    }

    public function translateBatch(array $input, string $translateFrom, string $translateTo, string $format = 'text'): array
    {
        $translateFrom = $this->sanitizeLanguageCode($translateFrom);
        $translateTo = $this->sanitizeLanguageCode($translateTo);

        $this->validateInput($input);

        $responses = $this->translateClient->translateBatch($input, $translateFrom, $translateTo, $format);

        return array_map(fn ($response) => [
            'source_text' => $response['input'],
            'source_language_code' => $translateFrom,
            'translated_text' => $response['text'],
            'translated_language_code' => $translateTo,
        ], $responses);
    }

    public function getAvailableTranslationsFor(string $languageCode): array
    {
        return $this->translateClient->getAvailableTranslationsFor($this->sanitizeLanguageCode($languageCode));
    }

    public function unlessLanguageIs(string $languageCode, string $input, ?string $from = null, ?string $to = null): array|string
    {
        $translateFrom = $this->sanitizeLanguageCode($from ?? config('google-translate-toolkit.default_source_translation'));
        $translateTo = $this->sanitizeLanguageCode($to ?? config('google-translate-toolkit.default_target_translation'));

        $languageCode = $this->sanitizeLanguageCode($languageCode);

        return $languageCode !== $this->detectLanguage($input)['language_code']
            ? $this->translate($input, $translateFrom, $translateTo)
            : $input;
    }

    public function sanitizeLanguageCode(string $languageCode): string
    {
        $languageCode = trim(strtolower($languageCode));

        if ($languageCode === 'zh-tw') {
            return 'zh-TW';
        }

        if (in_array($languageCode, $this->languages(), true)) {
            return $languageCode;
        }

        throw new Exception(
            "Invalid or unsupported ISO 639-1 language code -{$languageCode}-. Get the list of valid and supported language codes by running GoogleTranslate::languages()."
        );
    }

    protected function validateInput(mixed $input): void
    {
        if (is_array($input) && in_array(null, $input, true)) {
            throw new InvalidArgumentException('Input string cannot be null');
        }

        if ($input === null) {
            throw new InvalidArgumentException('Input string cannot be null');
        }
    }
}
