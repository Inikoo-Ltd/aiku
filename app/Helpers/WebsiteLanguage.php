<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The language a website visitor chose when it is not the shop's own (INI-510). It lives on the request,
 * so queued jobs, commands and the staff app never see it and always get the shop's own texts.
 * A text without a translation in that language falls back to the shop's own, field by field.
 */
final class WebsiteLanguage
{
    private const string REQUEST_KEY = 'website_translation_locale';

    public static function set(Shop $shop, string $locale): void
    {
        request()->attributes->set(self::REQUEST_KEY, $locale === $shop->language->code ? null : $locale);
    }

    public static function translateTo(): ?string
    {
        return request()->attributes->get(self::REQUEST_KEY);
    }

    public static function text(Model $model, string $field): ?string
    {
        $original = $model->getAttribute($field);
        $locale   = self::translateTo();

        if (!$locale) {
            return $original;
        }

        $translations = $model->getAttributes()[$field.'_i8n'] ?? null;
        if (is_string($translations)) {
            $translations = json_decode($translations, true);
        }

        $translated = is_array($translations) ? ($translations[$locale] ?? null) : null;

        return is_string($translated) && trim(strip_tags($translated)) !== '' ? $translated : $original;
    }

    /**
     * For raw queries: "products.name" becomes the visitor's language with the shop's text as fallback.
     */
    public static function column(string $column, ?string $alias = null): Expression
    {
        return DB::raw(self::sql($column, $alias));
    }

    public static function sql(string $column, ?string $alias = null): string
    {
        $locale = self::translateTo();
        $alias ??= str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;

        if (!$locale) {
            return "$column as $alias";
        }

        return 'coalesce(nullif('.$column.'_i8n->>'.DB::getPdo()->quote($locale).", ''), $column) as $alias";
    }
}
