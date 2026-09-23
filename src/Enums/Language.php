<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Enums;

use Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\UnsupportedLanguageException;
use Illuminate\Support\Collection;

/**
 * Every language code supported by the Google Cloud Translation v2 endpoint.
 */
enum Language: string
{
    case Afrikaans = 'af';
    case Akan = 'ak';
    case Albanian = 'sq';
    case Amharic = 'am';
    case Arabic = 'ar';
    case Armenian = 'hy';
    case Assamese = 'as';
    case Aymara = 'ay';
    case Azerbaijani = 'az';
    case Bambara = 'bm';
    case Basque = 'eu';
    case Belarusian = 'be';
    case Bengali = 'bn';
    case Bhojpuri = 'bho';
    case Bosnian = 'bs';
    case Bulgarian = 'bg';
    case Catalan = 'ca';
    case Cebuano = 'ceb';
    case ChineseSimplified = 'zh-CN';
    case ChineseTraditional = 'zh-TW';
    case Chinese = 'zh';
    case Corsican = 'co';
    case Croatian = 'hr';
    case Czech = 'cs';
    case Danish = 'da';
    case Dhivehi = 'dv';
    case Dogri = 'doi';
    case Dutch = 'nl';
    case English = 'en';
    case Esperanto = 'eo';
    case Estonian = 'et';
    case Ewe = 'ee';
    case Filipino = 'fil';
    case Finnish = 'fi';
    case French = 'fr';
    case Frisian = 'fy';
    case Galician = 'gl';
    case Georgian = 'ka';
    case German = 'de';
    case Greek = 'el';
    case Guarani = 'gn';
    case Gujarati = 'gu';
    case HaitianCreole = 'ht';
    case Hausa = 'ha';
    case Hawaiian = 'haw';
    case Hebrew = 'he';
    case HebrewLegacy = 'iw';
    case Hindi = 'hi';
    case Hmong = 'hmn';
    case Hungarian = 'hu';
    case Icelandic = 'is';
    case Igbo = 'ig';
    case Ilocano = 'ilo';
    case Indonesian = 'id';
    case Irish = 'ga';
    case Italian = 'it';
    case Japanese = 'ja';
    case Javanese = 'jv';
    case JavaneseLegacy = 'jw';
    case Kannada = 'kn';
    case Kazakh = 'kk';
    case Khmer = 'km';
    case Kinyarwanda = 'rw';
    case Konkani = 'gom';
    case Korean = 'ko';
    case Krio = 'kri';
    case Kurdish = 'ku';
    case KurdishSorani = 'ckb';
    case Kyrgyz = 'ky';
    case Lao = 'lo';
    case Latin = 'la';
    case Latvian = 'lv';
    case Lingala = 'ln';
    case Lithuanian = 'lt';
    case Luganda = 'lg';
    case Luxembourgish = 'lb';
    case Macedonian = 'mk';
    case Maithili = 'mai';
    case Malagasy = 'mg';
    case Malay = 'ms';
    case Malayalam = 'ml';
    case Maltese = 'mt';
    case Maori = 'mi';
    case Marathi = 'mr';
    case Meiteilon = 'mni-Mtei';
    case Mizo = 'lus';
    case Mongolian = 'mn';
    case Myanmar = 'my';
    case Nepali = 'ne';
    case Norwegian = 'no';
    case Nyanja = 'ny';
    case Odia = 'or';
    case Oromo = 'om';
    case Pashto = 'ps';
    case Persian = 'fa';
    case Polish = 'pl';
    case Portuguese = 'pt';
    case Punjabi = 'pa';
    case Quechua = 'qu';
    case Romanian = 'ro';
    case Russian = 'ru';
    case Samoan = 'sm';
    case Sanskrit = 'sa';
    case ScotsGaelic = 'gd';
    case Sepedi = 'nso';
    case Serbian = 'sr';
    case Sesotho = 'st';
    case Shona = 'sn';
    case Sindhi = 'sd';
    case Sinhala = 'si';
    case Slovak = 'sk';
    case Slovenian = 'sl';
    case Somali = 'so';
    case Spanish = 'es';
    case Sundanese = 'su';
    case Swahili = 'sw';
    case Swedish = 'sv';
    case Tagalog = 'tl';
    case Tajik = 'tg';
    case Tamil = 'ta';
    case Tatar = 'tt';
    case Telugu = 'te';
    case Thai = 'th';
    case Tigrinya = 'ti';
    case Tsonga = 'ts';
    case Turkish = 'tr';
    case Turkmen = 'tk';
    case Ukrainian = 'uk';
    case Urdu = 'ur';
    case Uyghur = 'ug';
    case Uzbek = 'uz';
    case Vietnamese = 'vi';
    case Welsh = 'cy';
    case Xhosa = 'xh';
    case Yiddish = 'yi';
    case Yoruba = 'yo';
    case Zulu = 'zu';

    /**
     * Normalize a loosely typed code: "EN_us" and "en-US" both become "en-US".
     */
    public static function normalize(self|string $code): string
    {
        if ($code instanceof self) {
            return $code->value;
        }

        $parts = preg_split('/[-_]/', trim($code)) ?: [];

        $language = mb_strtolower((string) array_shift($parts));

        if ($parts === []) {
            return $language;
        }

        $region = (string) array_shift($parts);

        return $language.'-'.(mb_strlen($region) === 2 ? mb_strtoupper($region) : ucfirst(mb_strtolower($region)));
    }

    public static function tryFromCode(self|string|null $code): ?self
    {
        if ($code instanceof self) {
            return $code;
        }

        if (blank($code)) {
            return null;
        }

        $normalized = self::normalize($code);

        return self::tryFrom($normalized)
            ?? self::tryFrom(mb_strtolower($normalized))
            ?? self::tryFrom(explode('-', $normalized)[0]);
    }

    public static function fromCode(self|string $code): self
    {
        return self::tryFromCode($code) ?? throw UnsupportedLanguageException::make((string) (is_string($code) ? $code : $code->value));
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return Collection<string, string> */
    public static function options(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn (self $language) => [$language->value => $language->label()]);
    }

    public function label(): string
    {
        return match ($this) {
            self::ChineseSimplified => 'Chinese (Simplified)',
            self::ChineseTraditional => 'Chinese (Traditional)',
            self::HaitianCreole => 'Haitian Creole',
            self::HebrewLegacy => 'Hebrew (legacy code)',
            self::JavaneseLegacy => 'Javanese (legacy code)',
            self::KurdishSorani => 'Kurdish (Sorani)',
            self::ScotsGaelic => 'Scots Gaelic',
            default => preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name) ?? $this->name,
        };
    }

    public function isRtl(): bool
    {
        return in_array($this, [
            self::Arabic, self::Hebrew, self::HebrewLegacy, self::Persian,
            self::Urdu, self::Pashto, self::Dhivehi, self::KurdishSorani, self::Uyghur,
        ], true);
    }
}
