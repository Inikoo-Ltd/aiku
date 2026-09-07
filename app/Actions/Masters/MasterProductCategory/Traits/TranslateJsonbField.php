<?php

/*
 * Author Louis Perez
 * Created on 20-08-2026-10h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Traits;

use App\Actions\Helpers\Translations\Translate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterProductCategory;

trait TranslateJsonbField
{
    private function getJsonbForShopLanguage(MasterProductCategory $masterProductCategory, ProductCategory $productCategory, ?Language $english, string $fieldName): array
    {
        $field = $masterProductCategory->{$fieldName} ?? [];

        $shopLanguage = $productCategory->shop->language;
        if (!$english || !$shopLanguage || $shopLanguage->code == 'en') {
            return $field;
        }

        $translatedField = Translate::run(json_encode($field), $english, $shopLanguage, 'gpt-5-nano');
        if (!is_string($translatedField)) {
            return $field;
        }

        $decodedField = json_decode($translatedField, true);

        return is_array($decodedField) ? $decodedField : $field;
    }
}
