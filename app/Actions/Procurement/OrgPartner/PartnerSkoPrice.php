<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Catalogue\Product\ProductStateEnum;
use Illuminate\Database\Query\Builder;

final class PartnerSkoPrice
{
    /**
     * An org stock is sold through several products: the plain box, multi-box packs and
     * mixed starter packs holding dozens of other SKOs. Only a product that holds this SKO
     * alone prices it, and of those the smallest, cheapest pack is the unit price;
     * anything else prices a whole bundle as if it were one SKO.
     */
    public static function pricePerSkoSql(string $orgStockIdExpression): string
    {
        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."'
            where phos.org_stock_id = ".$orgStockIdExpression."
                and (select count(*) from product_has_org_stocks bundle where bundle.product_id = pr.id) = 1
            order by phos.quantity, pr.price
            limit 1)";
    }

    public static function scopeToPricingProducts(Builder $query): Builder
    {
        return $query
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->whereRaw('(select count(*) from product_has_org_stocks bundle where bundle.product_id = products.id) = 1')
            ->orderBy('product_has_org_stocks.quantity')
            ->orderBy('products.price');
    }
}
