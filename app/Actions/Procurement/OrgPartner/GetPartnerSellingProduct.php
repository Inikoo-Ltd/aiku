<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerSellingProduct
{
    use AsObject;

    /**
     * The product a partner sells a stock with, from the first of the shops it sells to the other companies
     * from (see GetPartnerSellingShopIds) that sells it. Its pivot quantity is the SKOs it holds.
     *
     * @param  array<int, int>|null  $onlyShopIds  restricts the shops looked at, keeping their order of preference
     */
    public function handle(OrgPartner $orgPartner, int $stockId, ?array $onlyShopIds = null): ?Product
    {
        $shopIds = GetPartnerSellingShopIds::run($orgPartner->partner);
        if ($onlyShopIds !== null) {
            $shopIds = array_values(array_intersect($shopIds, $onlyShopIds));
        }
        if (!$shopIds) {
            return null;
        }

        $sellerOrgStocks = OrgStock::where('organisation_id', $orgPartner->partner_id)
            ->where('stock_id', $stockId)
            ->orderByRaw("state = 'discontinued'")
            ->orderBy('id')
            ->get();

        foreach ($sellerOrgStocks as $sellerOrgStock) {
            $products = $sellerOrgStock->products();
            PartnerSkoPrice::scopeToPricingProducts($products->getBaseQuery(), $shopIds);
            if ($product = $products->first()) {
                return $product;
            }
        }

        return null;
    }

    public function unitPrice(Product $product): ?float
    {
        $units = (float) ($product->pivot->quantity ?? 0) * (float) ($product->orgStocks()->first()?->packed_in ?: 1);

        return $units > 0 ? (float) $product->price / $units : null;
    }
}
