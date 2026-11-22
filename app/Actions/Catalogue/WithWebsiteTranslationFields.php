<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Model;

/**
 * The name and description of a product or category in the website's other languages (INI-510), so staff can
 * correct a machine translation. Fill commands only rewrite a text that still matches the hash they wrote, so
 * whatever staff save here is never replaced by the AI.
 */
trait WithWebsiteTranslationFields
{
    public const array WEBSITE_TRANSLATION_FIELDS = ['name_i8n', 'description_i8n'];

    public function websiteTranslationSection(Model $model, Shop $shop): ?array
    {
        $languages = Language::whereIn('code', array_diff($shop->websiteLanguageCodes(), [$shop->language->code]))
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();

        if (!$languages) {
            return null;
        }

        $field = fn (string $column, string $label, bool $html) => [
            'type'      => 'website_translations',
            'label'     => count($languages) === 1 ? $label.' ('.reset($languages).')' : $label,
            'full'      => true,
            'html'      => $html,
            'languages' => $languages,
            'value'     => array_map(fn (string $code) => (string) $model->getTranslation($column, $code, false), array_combine(array_keys($languages), array_keys($languages))),
        ];

        return [
            'label'  => __('Website languages'),
            'icon'   => 'fa-light fa-language',
            'fields' => [
                'name_i8n'        => $field('name_i8n', __('Name'), false),
                'description_i8n' => $field('description_i8n', __('Description'), true),
            ],
        ];
    }

    /**
     * A language left empty is removed instead of saved as "", so the fill still sees it as missing.
     */
    public function forgetBlankWebsiteTranslations(Model $model, array $modelData): array
    {
        foreach (self::WEBSITE_TRANSLATION_FIELDS as $column) {
            if (!is_array($modelData[$column] ?? null)) {
                continue;
            }
            foreach ($modelData[$column] as $locale => $text) {
                if (trim(html_entity_decode(strip_tags((string) $text)), " \t\n\r\0\x0B\u{A0}") === '') {
                    $model->forgetTranslation($column, $locale);
                    unset($modelData[$column][$locale]);
                }
            }
            if (!$modelData[$column]) {
                unset($modelData[$column]);
            }
        }

        return $modelData;
    }
}
