<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Per language with any product text, how many active products of the shop have a name and a
 * description in it, so a manager can see whether a language is ready before customers may choose it.
 * The shop's own language reads the plain columns.
 */
class GetShopLanguageReadiness
{
    use AsAction;

    /**
     * @return list<array{code: string, language: string, names: int, descriptions: int, products: int}>
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
                select jsonb_object_keys(coalesce(p.name_i8n, '{}'))
                union select jsonb_object_keys(coalesce(p.description_i8n, '{}'))
                union select cast(? as varchar)
            ) l(locale)
            where p.shop_id = ? and p.state = ? and p.deleted_at is null
            group by l.locale
            SQL, [$main, $main, $main, $shop->id, ProductStateEnum::ACTIVE->value]);

        $products = $shop->products()->where('state', ProductStateEnum::ACTIVE)->whereNull('deleted_at')->count();
        $names    = Language::whereIn('code', array_column($rows, 'locale'))->pluck('name', 'code');

        return collect($rows)
            ->filter(fn ($row) => $row->locale === $main || $row->names + $row->descriptions > 0)
            ->map(fn ($row) => [
                'code'         => $row->locale,
                'language'     => $names[$row->locale] ?? $row->locale,
                'names'        => (int) $row->names,
                'descriptions' => (int) $row->descriptions,
                'products'     => $products,
            ])
            ->sortBy([fn ($a, $b) => ($b['code'] === $main) <=> ($a['code'] === $main), ['names', 'desc']])
            ->values()
            ->all();
    }
}
