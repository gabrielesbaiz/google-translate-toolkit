# GoogleTranslateToolkit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gabrielesbaiz/google-translate-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/google-translate-toolkit)
[![Total Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/google-translate-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/google-translate-toolkit)

A lightweight helper package to handle translations using Google Translate API.

Original code from [JoggApp/laravel-google-translate](https://github.com/JoggApp/laravel-google-translate)

## Features

- ✅ Basic translation
- ✅ Advanced translation
- ✅ Detect language
- ✅ Batch detect language
- ✅ Batch detect language
- ✅ Batch translation
- ✅ Get available translations for a given text

## Installation

You can install the package via composer:

```bash
composer require gabrielesbaiz/google-translate-toolkit
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="google-translate-toolkit-config"
```

This is the contents of the published config file:

```php
return [
    /*
     * The ISO 639-1 code of the default source language.
     */
    'default_source_translation' => 'en',

    /*
     * The ISO 639-1 code of the language in lowercase to which
     * the text will be translated to by default.
     */
    'default_target_translation' => 'en',

    /*
     * Api Key generated within Google Cloud Dashboard.
     */
    'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
];
```

## Usage

```php
$googleTranslateToolkit = new Gabrielesbaiz\GoogleTranslateToolkit();

echo $googleTranslateToolkit->translate('Hello world');
```

Using facade:

```php
use GoogleTranslateToolkit;

GoogleTranslateToolkit::translate('Hello world');
```

## Available Methods
- `GoogleTranslateToolkit::detectLanguage(string $text): array` - Detect the language.
- `GoogleTranslateToolkit::detectLanguage(array $texts): array` - Detect the language.
- `GoogleTranslateToolkit::translate(string $text): array` - Translate the given text.
- `GoogleTranslateToolkit::translate(array $texts): array` - Translate the given text.
- `GoogleTranslateToolkit::justTranslate(string $text)): string` - Translate the given text.
- `GoogleTranslateToolkit::getAvailableTranslationsFor('en'): array` - Get all the available translations from 'Google Translation' for a particular language by passing its language code.
- `GoogleTranslateToolkit::unlessLanguageIs('en', string $text): array` - Translate unless the language is same as the first argument. It accepts an optional third argument which is the language code you want the string to be translated in. You can specify the default option in the config file. If the languages are same, the input string is returned as it is, else an array is returned containing the translation results.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jogg](https://github.com/Jogg)
- [Gabriele Sbaiz](https://github.com/gabrielesbaiz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
