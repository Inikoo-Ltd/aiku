<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\ProductCategory\FillShopCategoryTranslations;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Per language with any product text, how many active products of the shop have a name and a
 * description in it, so a manager can see whether a language is ready before customers may choose it.
 * The shop's own language reads the plain columns. The live departments, sub-departments, families and
 * collections (those products:fill-translations' sibling product-categories:fill-translations fills) are counted the same way.
 */
class GetShopLanguageReadiness
{
    use AsAction;

    /**
     * @return list<array{code: string, language: string, names: int, descriptions: int, products: int, category_names: int, category_descriptions: int, categories: int}>
     */
    public function handle(Shop $shop): array
    {
        $main = $shop->language->code;

        $rows = DB::select(<<<'SQL'
            select l.locale,
                   count(*) filter (where nullif(trim(coalesce(p.name_i8n->>l.locale, case when l.locale = ? then p.name end)), '') is not null) as names,
                   count(*) filter (where nullif(trim(coalesce(p.description_i8n->>l.locale, case when l.locale = ? then p.description end)), '') is not null) as descriptions
            from products p
            cross join lateral (
                select jsonb_object_keys(case when jsonb_typeof(p.name_i8n) = 'object' then p.name_i8n else '{}' end)
                union select jsonb_object_keys(case when jsonb_typeof(p.description_i8n) = 'object' then p.description_i8n else '{}' end)
                union select cast(? as varchar)
            ) l(locale)
            where p.shop_id = ? and p.state = ? and p.deleted_at is null
            group by l.locale
            SQL, [$main, $main, $main, $shop->id, ProductStateEnum::ACTIVE->value]);

        $products = $shop->products()->where('state', ProductStateEnum::ACTIVE)->whereNull('deleted_at')->count();
        $names    = Language::whereIn('code', array_column($rows, 'locale'))->pluck('name', 'code');
        $categories = $this->categoryCounts($shop, $main, array_column($rows, 'locale'));

        return collect($rows)
            ->filter(fn ($row) => $row->locale === $main || $row->names + $row->descriptions > 0)
            ->map(fn ($row) => [
                'code'         => $row->locale,
                'language'     => $names[$row->locale] ?? $row->locale,
                'names'        => (int) $row->names,
                'descriptions' => (int) $row->descriptions,
                'products'     => $products,
                'category_names'        => (int) ($categories[$row->locale]->names ?? 0),
                'category_descriptions' => (int) ($categories[$row->locale]->descriptions ?? 0),
                'categories'            => (int) ($categories[$row->locale]->total ?? 0),
            ])
            ->sortBy([fn ($a, $b) => ($b['code'] === $main) <=> ($a['code'] === $main), ['names', 'desc']])
            ->values()
            ->all();
    }

    /**
     * @param list<string> $locales
     * @return array<string, object{names: int, descriptions: int, total: int}>
     */
    private function categoryCounts(Shop $shop, string $main, array $locales): array
    {
        $states = fn (array $cases) => "'".implode("', '", array_map(fn ($case) => $case->value, $cases))."'";
        $types  = $states(FillShopCategoryTranslations::CATEGORY_TYPES);
        $live   = $states(FillShopCategoryTranslations::CATEGORY_STATES);

        $rows = DB::select(<<<SQL
            select l.locale,
                   count(*) as total,
                   count(*) filter (where nullif(trim(coalesce(c.name_i8n->>l.locale, case when l.locale = ? then c.name end)), '') is not null) as names,
                   count(*) filter (where nullif(trim(coalesce(c.description_i8n->>l.locale, case when l.locale = ? then c.description end)), '') is not null) as descriptions
            from (
                select name, name_i8n, description, description_i8n from product_categories
                where shop_id = ? and deleted_at is null and type in ($types) and state in ($live)
                union all
                select name, name_i8n, description, description_i8n from collections
                where shop_id = ? and deleted_at is null and state = ?
            ) c
            cross join unnest(cast(? as varchar[])) l(locale)
            group by l.locale
            SQL, [$main, $main, $shop->id, $shop->id, CollectionStateEnum::ACTIVE->value, '{'.implode(',', $locales).'}']);

        return collect($rows)->keyBy('locale')->all();
    }
}
