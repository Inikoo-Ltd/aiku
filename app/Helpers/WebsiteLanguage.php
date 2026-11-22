<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Tag;
use App\Models\Web\Webpage;
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

    private const array FAQ_TEXT_KEYS = ['question', 'answer'];

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
     * A webpage text (title, description, breadcrumb label, seo_data.image_alt) that is just the name of the page's product, category or
     * collection follows that name's translation; any other webpage text stays as written until it has its own.
     */
    public static function webpageText(Webpage $webpage, string $field): ?string
    {
        $text = data_get($webpage, $field);

        if (!is_string($text) || !$text || !self::translateTo()) {
            return $text;
        }

        $model = $webpage->model;
        if ($model && $text === $model->getAttribute('name')) {
            return self::text($model, 'name');
        }

        return WebsiteTexts::textForVisitor($webpage->website_id, $text);
    }

    /**
     * Tags are shared by every shop and already carry their names in each language in `label`.
     */
    public static function tagName(Tag $tag): ?string
    {
        $locale = self::translateTo();
        $label  = $locale && array_key_exists('label', $tag->getAttributes()) ? $tag->getTranslation('label', $locale, false) : null;

        return is_string($label) && trim($label) !== '' ? $label : $tag->name;
    }

    /**
     * A category FAQ (list of question/answer) in the visitor's language: each text is translated as a website text,
     * so it falls back to the shop's own string by string.
     */
    public static function faq(?int $websiteId, mixed $faq): mixed
    {
        if (!self::translateTo() || !$websiteId || !is_array($faq)) {
            return $faq;
        }

        return array_map(function ($item) use ($websiteId) {
            if (!is_array($item)) {
                return $item;
            }
            foreach (self::FAQ_TEXT_KEYS as $key) {
                if (is_string($item[$key] ?? null)) {
                    $item[$key] = WebsiteTexts::textForVisitor($websiteId, $item[$key]);
                }
            }

            return $item;
        }, $faq);
    }

    /**
     * @return array<string, string> source hash => text
     */
    public static function faqTexts(mixed $faq): array
    {
        $texts = [];
        foreach (is_array($faq) ? $faq : [] as $item) {
            foreach (self::FAQ_TEXT_KEYS as $key) {
                $text = is_array($item) ? ($item[$key] ?? null) : null;
                if (is_string($text) && WebsiteTexts::isWords($text)) {
                    $texts[WebsiteTexts::hash($text)] = $text;
                }
            }
        }

        return $texts;
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
