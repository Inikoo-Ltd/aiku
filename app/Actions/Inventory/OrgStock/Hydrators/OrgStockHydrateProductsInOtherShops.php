<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class OrgStockHydrateProductsInOtherShops
{
    use AsObject;

    public const string LISTING_SHOPS_CACHE_KEY = 'stock_from_other_warehouses:listing_shops';

    /**
     * INI-485. A change to an SKO re-hydrates the products of the other companies' shops that count this SKO's company's
     * stock and sell the same stock. While no shop lists the company it reads one cached map and does nothing else.
     */
    public function handle(OrgStock $orgStock): void
    {
        $shopIds = rescue(fn () => self::shopsListing()[$orgStock->organisation_id] ?? [], []);
        if (!$shopIds || !$orgStock->stock_id) {
            return;
        }

        rescue(fn () => DB::transaction(fn () => $this->hydrateProductsInOtherShops($orgStock, $shopIds)));
    }

    /**
     * @param  list<int>  $shopIds
     */
    private function hydrateProductsInOtherShops(OrgStock $orgStock, array $shopIds): void
    {
        $countingShopIds = Shop::whereIn('id', $shopIds)
            ->where('organisation_id', '!=', $orgStock->organisation_id)
            ->get()
            ->filter(fn (Shop $shop) => in_array($orgStock->organisation_id, array_column($shop->stockFromOtherWarehouses(), 'organisation_id')))
            ->modelKeys();
        if (!$countingShopIds) {
            return;
        }

        Product::whereIn('shop_id', $countingShopIds)
            ->whereHas('orgStocks', fn ($query) => $query->where('org_stocks.stock_id', $orgStock->stock_id))
            ->with(['group', 'organisation', 'shop', 'family', 'webpage', 'asset', 'orgStocks'])
            ->get()
            ->each(fn (Product $product) => ProductHydrateAvailableQuantity::dispatch($product));
    }

    /**
     * Which shops list each company in their stock_from_other_warehouses, before the direction and customs filters.
     * Kept 5 minutes, and forgotten whenever a shop saves its list.
     *
     * @return array<int, list<int>> organisation id => shop ids
     */
    public static function shopsListing(): array
    {
        return Cache::remember(self::LISTING_SHOPS_CACHE_KEY, now()->addMinutes(5), function (): array {
            $shopsListing = [];
            foreach (DB::transaction(fn () => Shop::whereNotNull('settings->stock_from_other_warehouses')->get(['id', 'settings'])) as $shop) {
                foreach (Shop::wellFormedStockFromOtherWarehouses(Arr::get($shop->settings, 'stock_from_other_warehouses')) as $entry) {
                    $shopsListing[$entry['organisation_id']][] = $shop->id;
                }
            }

            return $shopsListing;
        });
    }

    public static function forgetShopsListing(): void
    {
        Cache::forget(self::LISTING_SHOPS_CACHE_KEY);
    }
}
