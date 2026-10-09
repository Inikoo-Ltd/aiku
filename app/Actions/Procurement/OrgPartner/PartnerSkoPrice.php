<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
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
     * A product made exclusively for customers is their private label: a partner may buy it only
     * when its own intercompany customer is one of them. Everything else is off limits to the partner.
     */
    public static function notOtherCustomersExclusiveSql(string $productAlias, string $orgPartnerIdExpression): string
    {
        return "(not exists (select 1 from product_has_exclusive_customers exclusive where exclusive.product_id = $productAlias.id)
            or exists (select 1 from product_has_exclusive_customers exclusive
                join org_partners buyer on buyer.id = $orgPartnerIdExpression and jsonb_typeof(buyer.data->'intercompany_customers') = 'object'
                cross join jsonb_each_text(buyer.data->'intercompany_customers') own
                where exclusive.product_id = $productAlias.id and own.value::bigint = exclusive.customer_id))";
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
    public static function pricePerSkoSql(string $orgStockIdExpression, array $shopIds, string $orgPartnerIdExpression): string
    {
        $shopIds = $shopIds ?: [0];

        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."' and pr.shop_id in (".implode(',', array_map(intval(...), $shopIds)).")
            where phos.org_stock_id = ".$orgStockIdExpression."
                and (select count(*) from product_has_org_stocks bundle where bundle.product_id = pr.id) = 1
                and ".self::notOtherCustomersExclusiveSql('pr', $orgPartnerIdExpression)."
            order by ".self::shopPositionSql($shopIds, 'pr.shop_id').", phos.quantity, pr.price
            limit 1)";
    }

    /**
     * The seller's SKO is off limits to the partner when every product selling it is another
     * customer's private label: the partner never sees it nor can put it on its list or orders.
     */
    public static function offLimitsToPartnerSql(string $orgStockIdExpression, OrgPartner $orgPartner): string
    {
        $shopIds  = GetPartnerSellingShopIds::run($orgPartner->partner) ?: [0];
        $products = "select 1 from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."' and pr.shop_id in (".implode(',', array_map(intval(...), $shopIds)).")
            where phos.org_stock_id = $orgStockIdExpression";

        return "(exists ($products) and not exists ($products and ".self::notOtherCustomersExclusiveSql('pr', (string) $orgPartner->id)."))";
    }

    public static function isOffLimitsToPartner(OrgPartner $orgPartner, int $stockId): bool
    {
        return OrgStock::where('organisation_id', $orgPartner->partner_id)
            ->where('stock_id', $stockId)
            ->whereRaw(self::offLimitsToPartnerSql('org_stocks.id', $orgPartner))
            ->exists();
    }

    /**
     * @param  array<int, int>|null  $shopIds  shops in order of preference; the first one that sells the SKO wins
     */
    public static function scopeToPricingProducts(Builder $query, ?array $shopIds, int $orgPartnerId): Builder
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
            ->whereRaw(self::notOtherCustomersExclusiveSql('products', (string) $orgPartnerId))
            ->orderBy('product_has_org_stocks.quantity')
            ->orderBy('products.price');
    }
}
