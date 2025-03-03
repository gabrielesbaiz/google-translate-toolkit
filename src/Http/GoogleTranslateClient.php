<?php

namespace Gabrielesbaiz\GoogleTranslateToolkit\Http;

use Exception;
use Google\Cloud\Translate\V2\TranslateClient;
use Gabrielesbaiz\GoogleTranslateToolkit\Traits\SupportedLanguages;

class GoogleTranslateClient
{
    use SupportedLanguages;

    private TranslateClient $translate;

    public function __construct(array $config)
    {
        $this->validateConfiguration($config);

        $this->translate = new TranslateClient([
            'key' => $config['api_key'],
        ]);
    }

    public function detectLanguage(string $text): array
    {
        return $this->translate->detectLanguage($text);
    }

    public function detectLanguageBatch(array $input): array
    {
        return $this->translate->detectLanguageBatch($input);
    }

    public function translate(string $text, string $translateFrom, string $translateTo, string $format = 'text'): array
    {
        return $this->translate->translate($text, [
            'source' => $translateFrom,
            'target' => $translateTo,
            'format' => $format,
        ]);
    }

    public function translateBatch(array $input, string $translateFrom, string $translateTo, string $format = 'text'): array
    {
        return $this->translate->translateBatch($input, [
            'source' => $translateFrom,
            'target' => $translateTo,
            'format' => $format,
        ]);
    }

    public function getAvailableTranslationsFor(string $languageCode): array
    {
        return $this->translate->localizedLanguages([
            'target' => $languageCode,
        ]);
    }

    private function validateConfiguration(array $config): void
    {
        if (empty($config['api_key'])) {
            throw new Exception('Google API key is required.');
        }

        $codeInConfig = $config['default_target_translation'];

        $languageCodeIsValid = is_string($codeInConfig) &&
            ctype_lower($codeInConfig) &&
            in_array($codeInConfig, $this->languages());

        if (! $languageCodeIsValid) {
            throw new Exception(
                'The "default_target_translation" value in config/google-translate-toolkit.php must be a valid lowercase ISO 639-1 language code.'
            );
        }
    }
}
