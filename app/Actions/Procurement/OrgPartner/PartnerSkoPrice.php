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
     * @param  array<int, int>  $shopIds
     */
    public static function shopPositionSql(array $shopIds, string $shopIdExpression): string
    {
        return 'array_position(ARRAY['.implode(',', array_map(intval(...), $shopIds)).']::int[], '.$shopIdExpression.')';
    }

    /**
     * Products sharing an SKO at the same pack size tie on everything but price, and the cheaper one is
     * often a customer's special label (PREOSC-01 beside PrEO-01). The product carrying the SKO's own
     * code is the one the partner sells it as.
     */
    private static function notNamedAfterSkoSql(string $productCodeExpression, string $orgStockIdExpression): string
    {
        return "lower($productCodeExpression) is distinct from (select lower(sko.code) from org_stocks sko where sko.id = $orgStockIdExpression)";
    }

    /**
     * An org stock is sold through several products: the plain box, multi-box packs and
     * mixed starter packs holding dozens of other SKOs. Only a product that holds this SKO
     * alone prices it, and of those the smallest, cheapest pack is the unit price;
     * anything else prices a whole bundle as if it were one SKO. Only the listed shops price it,
     * the first of them that sells it winning.
     *
     * @param  array<int, int>  $shopIds
     */
    public static function pricePerSkoSql(string $orgStockIdExpression, array $shopIds): string
    {
        $shopIds = $shopIds ?: [0];

        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."' and pr.shop_id in (".implode(',', array_map(intval(...), $shopIds)).")
            where phos.org_stock_id = ".$orgStockIdExpression."
                and (select count(*) from product_has_org_stocks bundle where bundle.product_id = pr.id) = 1
            order by ".self::shopPositionSql($shopIds, 'pr.shop_id').", phos.quantity, ".self::notNamedAfterSkoSql('pr.code', 'phos.org_stock_id').", pr.price
            limit 1)";
    }

    /**
     * @param  array<int, int>|null  $shopIds  shops in order of preference; the first one that sells the SKO wins
     */
    public static function scopeToPricingProducts(Builder $query, ?array $shopIds = null): Builder
    {
        if ($shopIds !== null) {
            $query->whereIn('products.shop_id', $shopIds);
            if ($shopIds) {
                $query->orderByRaw(self::shopPositionSql($shopIds, 'products.shop_id'));
            }
        }

        return $query
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->whereRaw('(select count(*) from product_has_org_stocks bundle where bundle.product_id = products.id) = 1')
            ->orderBy('product_has_org_stocks.quantity')
            ->orderByRaw(self::notNamedAfterSkoSql('products.code', 'product_has_org_stocks.org_stock_id'))
            ->orderBy('products.price');
    }
}
