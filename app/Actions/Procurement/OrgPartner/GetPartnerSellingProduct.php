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
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerSellingProduct
{
    use AsObject;

    /**
     * The product a partner sells a stock with, in the shop it sells to the other companies from
     * (settings procurement.shop_id). Its pivot quantity is the SKOs it holds.
     */
    public function handle(OrgPartner $orgPartner, int $stockId): ?Product
    {
        $shopId = Arr::get($orgPartner->partner->settings, 'procurement.shop_id');
        if (!$shopId) {
            return null;
        }

        $sellerOrgStock = OrgStock::where('organisation_id', $orgPartner->partner_id)
            ->where('stock_id', $stockId)
            ->first();
        if (!$sellerOrgStock) {
            return null;
        }

        $products = $sellerOrgStock->products()->where('products.shop_id', $shopId);
        PartnerSkoPrice::scopeToPricingProducts($products->getBaseQuery());

        return $products->first();
    }

    public function unitPrice(Product $product): ?float
    {
        $units = (float) ($product->pivot->quantity ?? 0) * (float) ($product->orgStocks()->first()?->packed_in ?: 1);

        return $units > 0 ? (float) $product->price / $units : null;
    }
}
