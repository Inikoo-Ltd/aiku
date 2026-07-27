<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Masters\MasterShop\RecalculateMasterShopMinorCurrencyPrices;
use App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket;
use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Glues shop prices back to their master, but only where there is nothing to interpret.
 *
 * A product qualifies only when it agrees with its master on BOTH units and trade-unit
 * composition (ids and quantities). Any disagreement means the two sides describe a
 * different thing, so a price copied across would be comparing unlike baskets - those are
 * left alone for the units reconciliation to settle first. Declared rebels at shop, family
 * or product level are never touched, nor is anything discontinued or not for sale.
 *
 * Scoped to one currency at a time so a master shop with several majors is reconciled
 * deliberately rather than all at once.
 */
class SyncMasterChildPricesWhereUnambiguous
{
    use AsAction;

    public string $commandSignature = 'repair:master_child_prices {master_shop : master shop slug} {--currency=EUR : shop currency to reconcile} {--shop= : limit to one shop code, all shops of that currency if omitted} {--fix : Write corrections (default is report only)}';

    /** @var array<int, int> shop ids that actually had a product corrected */
    private array $touchedShopIDs = [];

    /**
     * @return array{checked: int, price: int, rrp: int}
     */
    public function handle(MasterShop $masterShop, string $currencyCode, bool $fix = false, ?Command $command = null, ?string $shopCode = null): array
    {
        $counts               = ['checked' => 0, 'price' => 0, 'rrp' => 0];
        $this->touchedShopIDs = [];

        $this->qualifyingProducts($masterShop, $currencyCode, $shopCode)
            ->chunkById(250, function ($products) use ($currencyCode, $fix, $command, &$counts) {
                foreach ($products as $product) {
                    $counts['checked']++;
                    $this->syncProduct($product, $currencyCode, $fix, $command, $counts);
                }
            }, 'products.id', 'id');

        if ($fix && $this->touchedShopIDs) {
            $this->finalise($command);
        }

        return $counts;
    }

    /**
     * Products are written with UpdateProduct::$bulkPriceUpdate, which suppresses the per-product
     * cache break, Luigi/Scout reindex and basket recalculation so a run of this size does not fire
     * one varnish ban per product. Those have to happen once at the end instead, for the shops that
     * actually changed - same closing sequence as FinaliseRecalculateMasterShopMinorCurrencyPrices.
     */
    private function finalise(?Command $command): void
    {
        $shops = Shop::whereIn('id', $this->touchedShopIDs)->get();

        $command?->info('Breaking website caches for '.$shops->count().' shop(s)');
        RecalculateMasterShopMinorCurrencyPrices::breakWebsitesCache($shops, CrawlTriggerEnum::WEBSITE_UPDATE);

        $command?->info('Dispatching Luigi reindex');
        Product::whereIn('shop_id', $shops->pluck('id'))
            ->whereNotNull('webpage_id')
            ->pluck('webpage_id')
            ->unique()
            ->each(fn ($webpageID) => ReindexWebpageLuigiData::dispatch($webpageID)->delay(60));

        $command?->info('Queueing Scout reindex');
        Product::whereIn('shop_id', $shops->pluck('id'))->searchable();

        $orderIDs = Order::whereIn('shop_id', $shops->pluck('id'))
            ->where('state', OrderStateEnum::CREATING)
            ->pluck('id');

        $command?->info('Repricing '.$orderIDs->count().' basket(s)');
        foreach ($orderIDs as $orderID) {
            RecalculateTotalsOrdersInBasket::dispatch($orderID);
        }
    }

    /**
     * Products whose units and trade-unit composition both match their master, so the master
     * price describes the same basket the shop is selling.
     */
    private function qualifyingProducts(MasterShop $masterShop, string $currencyCode, ?string $shopCode = null)
    {
        $signature = fn (string $type, string $idColumn) => "select string_agg(trade_unit_id || ':' || quantity, ',' order by trade_unit_id)
             from model_has_trade_units
             where model_type = '$type' and model_id = $idColumn";

        return Product::query()
            ->join('master_assets', 'master_assets.id', '=', 'products.master_product_id')
            ->join('currencies', 'currencies.id', '=', 'products.currency_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.family_id')
            ->where('master_assets.master_shop_id', $masterShop->id)
            ->where('master_assets.status', true)
            ->whereNull('master_assets.deleted_at')
            ->where('currencies.code', $currencyCode)
            ->when($shopCode, fn ($query) => $query->where('shops.code', $shopCode))
            ->where('products.is_for_sale', true)
            ->where('products.not_follow_master_prices', false)
            ->where('products.not_follow_master_trade_units', false)
            ->where(fn ($query) => $query->whereNull('product_categories.not_follow_master_prices')
                ->orWhere('product_categories.not_follow_master_prices', false))
            ->whereRaw("coalesce((shops.settings #>> '{catalog,follow_master_pricing}')::boolean, true)")
            ->whereRaw('round(products.units, 3) = round(master_assets.units, 3)')
            ->whereRaw('('.$signature('Product', 'products.id').') is not null')
            ->whereRaw('('.$signature('Product', 'products.id').') = ('.$signature('MasterAsset', 'master_assets.id').')')
            ->select('products.*')
            ->with('masterProduct')
            ->orderBy('products.id');
    }

    private function syncProduct(Product $product, string $currencyCode, bool $fix, ?Command $command, array &$counts): void
    {
        /** @var MasterAsset $masterAsset */
        $masterAsset = $product->masterProduct;

        $masterPrice = data_get($masterAsset->master_prices, "$currencyCode.value");
        $masterRrp   = data_get($masterAsset->master_rrps, "$currencyCode.value");

        $modelData = [];

        if ($masterPrice !== null && round((float) $product->price, 2) !== round((float) $masterPrice, 2)) {
            $modelData['price'] = $masterPrice;
            $counts['price']++;
        }

        if ($masterRrp !== null && round((float) $product->rrp, 2) !== round((float) $masterRrp, 2)) {
            $modelData['rrp'] = $masterRrp;
            $counts['rrp']++;
        }

        if (!$modelData) {
            return;
        }

        $command?->line(sprintf(
            '%s %s: %s%s',
            $product->shop->code,
            $product->code,
            isset($modelData['price']) ? "price $product->price -> {$modelData['price']} " : '',
            isset($modelData['rrp']) ? "rrp $product->rrp -> {$modelData['rrp']}" : ''
        ));

        if ($fix) {
            $updateProduct                  = UpdateProduct::make();
            $updateProduct->bulkPriceUpdate = true;
            $updateProduct->action($product, $modelData);

            $this->touchedShopIDs[$product->shop_id] = $product->shop_id;
        }
    }

    public function asCommand(Command $command): int
    {
        ini_set('memory_limit', '2G');
        DB::connection()->disableQueryLog();

        $masterShop   = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $currencyCode = strtoupper($command->option('currency'));
        $shopCode     = $command->option('shop');
        $fix          = (bool) $command->option('fix');

        if ($shopCode && !Shop::where('code', $shopCode)->exists()) {
            $command->error("No shop with code $shopCode");

            return 1;
        }

        if (!$fix) {
            $command->warn('REPORT ONLY: pass --fix to write corrections');
        }

        $counts = $this->handle($masterShop, $currencyCode, $fix, $command, $shopCode);

        $command->info(sprintf(
            'Done: %d %s products checked%s, %d prices and %d rrps %s',
            $counts['checked'],
            $currencyCode,
            $shopCode ? " in $shopCode" : '',
            $counts['price'],
            $counts['rrp'],
            $fix ? 'corrected' : 'out of sync'
        ));

        return 0;
    }
}
