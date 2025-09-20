<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 26 Aug 2021 04:30:57 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace Database\Seeders;

use App\Models\Helpers\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $validLanguages = [
            'cs',
            'de',
            'en',
            'es',
            'fr',
            'hr',
            'hu',
            'id',
            'ja',
            'nl',
            'pl',
            'pt',
            'sk',
            'sv',
            'zh-Hans',
            'bg',
            'uk',
        ];

        // Map of native/original names for key languages we support by default
        $nativeNames = [
            'cs'      => 'Čeština',
            'de'      => 'Deutsch',
            'en'      => 'English',
            'es'      => 'Español',
            'fr'      => 'Français',
            'hr'      => 'Hrvatski',
            'hu'      => 'Magyar',
            'id'      => 'Bahasa Indonesia',
            'ja'      => '日本語',
            'nl'      => 'Nederlands',
            'pl'      => 'Polski',
            'pt'      => 'Português',
            'sk'      => 'Slovenčina',
            'sv'      => 'Svenska',
            'zh-Hans' => '简体中文',
            'bg'      => 'Български',
            'uk'      => 'Українська',
        ];

        /*
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.poeditor.com/v2/languages/available");
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'api_token' => config('app.po_editor_api_key'),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        $languages=$response->result->languages;
        Storage::disk('datasets')->put('datasets/languages.json', json_encode($languages));
        */
        $languages = json_decode(Storage::disk('datasets')->get('languages.json'));

        // Build a quick lookup of available flag filenames in public/flags (e.g., 'ae.png')
        $availableFlags = [];
        foreach (glob(base_path('public/flags/*.png')) as $flagPath) {
            $availableFlags[strtolower(basename($flagPath))] = true;
        }

        foreach ($languages as $language) {
            $code         = $language->code;
            $originalName = $language->native_name ?? ($nativeNames[$code] ?? $language->name);

            // Determine flag: keep a provided flag if present; otherwise infer from code and available flags
            $flag = $language->flag ?? null;
            if (!$flag) {
                // Region-specific: use country/region part after '-' if it exists (e.g., ar-AE -> ae.png)
                if (str_contains($code, '-')) {
                    $region = strtolower(substr($code, strrpos($code, '-') + 1));
                    $candidate = $region . '.png';
                    if (isset($availableFlags[$candidate])) {
                        $flag = $candidate;
                    }
                }
                // If still not set, try direct language code (e.g., am -> am.png) when available
                if (!$flag) {
                    $candidate = strtolower($code) . '.png';
                    if (isset($availableFlags[$candidate])) {
                        $flag = $candidate;
                    }
                }
            }

            Language::upsert(
                [
                    [
                        'code'        => $code,
                        'name'        => $language->name,
                        'status'      => in_array($code, $validLanguages),
                        'data'        => json_encode([]),
                        'flag'        => $flag,
                        'native_name' => $originalName,
                    ],
                ],
                ['code'],
                ['name', 'status', 'native_name', 'flag']
            );
        }
    }
}
