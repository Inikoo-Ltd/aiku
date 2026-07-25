<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild;
use App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket;
use App\Actions\Web\Website\BreakWebsiteCache;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use Illuminate\Support\Collection;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateMasterShopMinorCurrencyPrices implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default';

    public function getJobUniqueId(MasterShop $masterShop, string $currencyCode): string
    {
        return $masterShop->id.'-'.$currencyCode;
    }

    public function handle(MasterShop $masterShop, string $currencyCode): void
    {
        $exchangeData = data_get($masterShop->price_exchanges, $currencyCode);

        if (!$exchangeData || ($exchangeData['is_major'] ?? false)) {
            return;
        }

        $majorCurrencyCode = $exchangeData['major'] ?? null;
        $exchange          = $exchangeData['exchange'] ?? null;

        if (!$majorCurrencyCode || !$exchange) {
            return;
        }

        $affectedShops = $masterShop->shops()
            ->where('state', ShopStateEnum::OPEN)
            ->whereHas('currency', fn ($query) => $query->where('code', $currencyCode))
            ->get();

        $lastCacheBreak = microtime(true);

        $masterShop->masterAssets()
            ->where('is_main', true)
            ->chunkById(200, function ($masterAssets) use ($currencyCode, $majorCurrencyCode, $exchange, $affectedShops, &$lastCacheBreak) {
                /** @var MasterAsset $masterAsset */
                foreach ($masterAssets as $masterAsset) {
                    $this->recalculateMasterAsset($masterAsset, $currencyCode, $majorCurrencyCode, $exchange);
                }

                if (microtime(true) - $lastCacheBreak >= 60) {
                    $this->breakWebsitesCache($affectedShops, null);
                    $lastCacheBreak = microtime(true);
                }
            });

        $this->breakWebsitesCache($affectedShops, CrawlTriggerEnum::WEBSITE_UPDATE);

        foreach ($affectedShops as $shop) {
            Order::where('shop_id', $shop->id)
                ->where('state', OrderStateEnum::CREATING)
                ->pluck('id')
                ->each(fn ($orderID) => RecalculateTotalsOrdersInBasket::dispatch($orderID));
        }
    }

    protected function breakWebsitesCache(Collection $affectedShops, ?CrawlTriggerEnum $crawlTrigger): void
    {
        foreach ($affectedShops as $shop) {
            if ($shop->website) {
                BreakWebsiteCache::run($shop->website, $crawlTrigger);
            }
        }
    }

    protected function recalculateMasterAsset(MasterAsset $masterAsset, string $currencyCode, string $majorCurrencyCode, float $exchange): void
    {
        $modelData = [];

        foreach (['master_prices', 'master_rrps'] as $field) {
            $values = $masterAsset->{$field} ?? [];

            if (data_get($values, "$currencyCode.independent")) {
                continue;
            }

            $majorValue = data_get($values, "$majorCurrencyCode.value");
            if (!$majorValue) {
                continue;
            }

            $newValue = formatPrice($majorValue, $exchange);
            if ((float)$newValue <= 0 || (string)data_get($values, "$currencyCode.value") === $newValue) {
                continue;
            }

            data_set($values, "$currencyCode.value", $newValue);
            $modelData[$field] = $values;
        }

        if (!$modelData) {
            return;
        }

        $masterAsset->updateQuietly($modelData);
        MasterAssetHydrateMasterPricesRRPtoChild::run($masterAsset, null, skipBreakWebpageCache: true);
    }
}
