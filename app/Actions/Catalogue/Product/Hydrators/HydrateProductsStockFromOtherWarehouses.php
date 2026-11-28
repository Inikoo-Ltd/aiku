<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsInOtherShops;
use App\Actions\Ordering\Order\UpdateState\RerouteOrderHeldForOtherWarehouse;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use App\Actions\Traits\AsUniqueUntilProcessingAction;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nightwatch\Facades\Nightwatch;

class HydrateProductsStockFromOtherWarehouses implements ShouldBeUniqueUntilProcessing
{
    use AsUniqueUntilProcessingAction;

    public string $jobQueue = 'stock-control';

    public string $commandSignature = 'products:hydrate-stock-from-other-warehouses';

    public function getJobUniqueId(?Shop $shop = null): string
    {
        return $shop?->id ?? 'all';
    }

    /**
     * INI-485. Queues the stock figure of every product a change in the other warehouses could move: a shop's products
     * when its list or a direction it counts changed, or, with no shop, the nightly reconcile of every shop that lists
     * another warehouse and every product still selling stock from elsewhere. Nothing is queued while no shop lists one.
     */
    public function handle(?Shop $shop = null): int
    {
        $query = $shop ? Product::where('shop_id', $shop->id) : $this->reconcileQuery();

        $queued = 0;
        $query->select('id')->chunkById(500, function ($products) use (&$queued) {
            foreach ($products as $product) {
                ProductHydrateAvailableQuantity::dispatch($product);
                $queued++;
            }
        });

        return $queued;
    }

    /**
     * @return Builder<Product>
     */
    private function reconcileQuery(): Builder
    {
        OrgStockHydrateProductsInOtherShops::forgetShopsListing();
        $listingShopIds = collect(OrgStockHydrateProductsInOtherShops::shopsListing())->flatten()->unique()->values()->all();

        return Product::where(fn (Builder $query) => $query->whereRaw('available_quantity_elsewhere > 0')
            ->when($listingShopIds, fn (Builder $query) => $query->orWhereIn('shop_id', $listingShopIds)));
    }

    /**
     * Every product of the shops of the ordering company that list the dispatching company, after a direction between them changed.
     */
    public static function dispatchForShopsListing(int $originOrganisationId, int $dispatcherOrganisationId): void
    {
        OrgStockHydrateProductsInOtherShops::forgetShopsListing();
        $shopIds = OrgStockHydrateProductsInOtherShops::shopsListing()[$dispatcherOrganisationId] ?? [];
        if (!$shopIds) {
            return;
        }

        Shop::whereIn('id', $shopIds)->where('organisation_id', $originOrganisationId)->get()->each(fn (Shop $shop) => self::dispatch($shop));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $command->info('Queued '.$this->handle().' products and '.RerouteOrderHeldForOtherWarehouse::dispatchForAll().' orders held for another warehouse.');

        return 0;
    }
}
