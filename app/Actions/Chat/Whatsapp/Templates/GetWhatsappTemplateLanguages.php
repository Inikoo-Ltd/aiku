<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * WhatsApp accepts its own language codes, which only partly match the ones in Aiku's
 * languages table: Aiku stores `zh-Hans` where WhatsApp wants `zh_CN`, and `pt` where
 * WhatsApp only knows `pt_BR` and `pt_PT`. So the supported set below is the authority
 * on what may be submitted, and Aiku's table supplies the names and flags wherever the
 * two agree — a template in an unlisted language is rejected by Meta outright.
 */
class GetWhatsappTemplateLanguages
{
    use AsObject;

    /**
     * WhatsApp code => [Aiku language code, fallback label]
     */
    public const SUPPORTED = [
        'af'    => ['af', 'Afrikaans'],
        'sq'    => ['sq', 'Albanian'],
        'ar'    => ['ar', 'Arabic'],
        'az'    => ['az', 'Azerbaijani'],
        'bn'    => ['bn', 'Bengali'],
        'bg'    => ['bg', 'Bulgarian'],
        'ca'    => ['ca', 'Catalan'],
        'zh_CN' => ['zh-Hans', 'Chinese (China)'],
        'zh_HK' => ['zh-Hant', 'Chinese (Hong Kong)'],
        'zh_TW' => ['zh-Hant', 'Chinese (Taiwan)'],
        'hr'    => ['hr', 'Croatian'],
        'cs'    => ['cs', 'Czech'],
        'da'    => ['da', 'Danish'],
        'nl'    => ['nl', 'Dutch'],
        'en'    => ['en', 'English'],
        'en_GB' => ['en', 'English (UK)'],
        'en_US' => ['en', 'English (US)'],
        'et'    => ['et', 'Estonian'],
        'fil'   => ['fil', 'Filipino'],
        'fi'    => ['fi', 'Finnish'],
        'fr'    => ['fr', 'French'],
        'ka'    => ['ka', 'Georgian'],
        'de'    => ['de', 'German'],
        'el'    => ['el', 'Greek'],
        'gu'    => ['gu', 'Gujarati'],
        'ha'    => ['ha', 'Hausa'],
        'he'    => ['he', 'Hebrew'],
        'hi'    => ['hi', 'Hindi'],
        'hu'    => ['hu', 'Hungarian'],
        'id'    => ['id', 'Indonesian'],
        'ga'    => ['ga', 'Irish'],
        'it'    => ['it', 'Italian'],
        'ja'    => ['ja', 'Japanese'],
        'kn'    => ['kn', 'Kannada'],
        'kk'    => ['kk', 'Kazakh'],
        'ko'    => ['ko', 'Korean'],
        'lo'    => ['lo', 'Lao'],
        'lv'    => ['lv', 'Latvian'],
        'lt'    => ['lt', 'Lithuanian'],
        'mk'    => ['mk', 'Macedonian'],
        'ms'    => ['ms', 'Malay'],
        'ml'    => ['ml', 'Malayalam'],
        'mr'    => ['mr', 'Marathi'],
        'nb'    => ['nb', 'Norwegian'],
        'fa'    => ['fa', 'Persian'],
        'pl'    => ['pl', 'Polish'],
        'pt_BR' => ['pt', 'Portuguese (Brazil)'],
        'pt_PT' => ['pt', 'Portuguese (Portugal)'],
        'pa'    => ['pa', 'Punjabi'],
        'ro'    => ['ro', 'Romanian'],
        'ru'    => ['ru', 'Russian'],
        'sr'    => ['sr', 'Serbian'],
        'sk'    => ['sk', 'Slovak'],
        'sl'    => ['sl', 'Slovenian'],
        'es'    => ['es', 'Spanish'],
        'es_AR' => ['es', 'Spanish (Argentina)'],
        'es_ES' => ['es', 'Spanish (Spain)'],
        'es_MX' => ['es', 'Spanish (Mexico)'],
        'sw'    => ['sw', 'Swahili'],
        'sv'    => ['sv', 'Swedish'],
        'ta'    => ['ta', 'Tamil'],
        'te'    => ['te', 'Telugu'],
        'th'    => ['th', 'Thai'],
        'tr'    => ['tr', 'Turkish'],
        'uk'    => ['uk', 'Ukrainian'],
        'ur'    => ['ur', 'Urdu'],
        'uz'    => ['uz', 'Uzbek'],
        'vi'    => ['vi', 'Vietnamese'],
        'zu'    => ['zu', 'Zulu'],
    ];

    /**
     * Languages the shop already trades in float to the top, since a template is almost
     * always written for one of those.
     *
     * @return array<int, array{value: string, label: string, flag: string|null, is_active: bool}>
     */
    public function handle(): array
    {
        $aikuCodes = collect(self::SUPPORTED)->map(fn (array $entry) => $entry[0])->unique()->all();

        $languages = Language::whereIn('code', $aikuCodes)->get()->keyBy('code');

        return collect(self::SUPPORTED)
            ->map(function (array $entry, string $whatsappCode) use ($languages) {
                [$aikuCode, $fallbackLabel] = $entry;
                $language = $languages->get($aikuCode);

                // Regional variants keep the hand-written label: Aiku only stores one row
                // for "English", which would make en_GB and en_US indistinguishable.
                $isVariant = str_contains($whatsappCode, '_');

                return [
                    'value'     => $whatsappCode,
                    'label'     => $isVariant ? $fallbackLabel : ($language?->name ?? $fallbackLabel),
                    'flag'      => $language?->flag,
                    'is_active' => (bool) $language?->status,
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => ($b['is_active'] <=> $a['is_active']),
                fn (array $a, array $b) => ($a['label'] <=> $b['label']),
            ])
            ->values()
            ->all();
    }
}
