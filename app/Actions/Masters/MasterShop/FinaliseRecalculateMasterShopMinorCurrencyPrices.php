<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 01:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket;
use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class FinaliseRecalculateMasterShopMinorCurrencyPrices
{
    use AsAction;

    public string $jobQueue = 'price_change_control';

    public function jobFailed(\Throwable $e, MasterShop $masterShop, string $currencyCode): void
    {
        RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, [
            'state' => 'failed',
            'done'  => (int)data_get(RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode), 'done'),
            'total' => (int)data_get(RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode), 'total'),
            'error' => $e->getMessage(),
        ]);
        RecalculateMasterShopMinorCurrencyPrices::forgetProgress($masterShop, $currencyCode);
    }

    public function handle(MasterShop $masterShop, string $currencyCode, ?int $userID = null): void
    {
        if ($userID) {
            Auth::guard('web')->loginUsingId($userID);
        }

        $previousMemoryLimit = ini_set('memory_limit', '2048M');

        try {
            $this->finalise($masterShop, $currencyCode, $userID);
        } finally {
            if ($previousMemoryLimit !== false) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }
    }

    private function finalise(MasterShop $masterShop, string $currencyCode, ?int $userID): void
    {
        $affectedShops = RecalculateMasterShopMinorCurrencyPrices::getAffectedShops($masterShop, $currencyCode);
        $progress      = RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode) ?? [
            'done'  => 0,
            'total' => 0,
        ];

        RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, array_merge($progress, [
            'state' => 'breaking_cache',
        ]));

        Log::info("price-finalize [$currencyCode] breaking cache");
        RecalculateMasterShopMinorCurrencyPrices::breakWebsitesCache($affectedShops, CrawlTriggerEnum::WEBSITE_UPDATE);
        Log::info("price-finalize [$currencyCode] cache broken, dispatching luigi");

        Product::whereIn('shop_id', $affectedShops->pluck('id'))
            ->whereNotNull('webpage_id')
            ->pluck('webpage_id')
            ->unique()
            ->each(fn ($webpageID) => ReindexWebpageLuigiData::dispatch($webpageID)->delay(60));

        Log::info("price-finalize [$currencyCode] luigi dispatched, scout reindex");
        Product::whereIn('shop_id', $affectedShops->pluck('id'))->searchable();
        Log::info("price-finalize [$currencyCode] scout queued, repricing baskets");

        $orderIDs = Order::whereIn('shop_id', $affectedShops->pluck('id'))
            ->where('state', OrderStateEnum::CREATING)
            ->pluck('id');

        if ($orderIDs->isEmpty()) {
            RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, array_merge($progress, [
                'state'         => 'finished',
                'baskets_total' => 0,
                'baskets_done'  => 0,
                'finished_at'   => now()->toIso8601String(),
            ]));
            RecalculateMasterShopMinorCurrencyPrices::forgetProgress($masterShop, $currencyCode);

            if ($userID) {
                Auth::guard('web')->logout();
            }

            return;
        }

        RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, array_merge($progress, [
            'state'              => 'repricing_baskets',
            'baskets_total'      => $orderIDs->count(),
            'baskets_done'       => 0,
            'baskets_started_at' => now()->toIso8601String(),
        ]));
        Cache::put(RecalculateMasterShopMinorCurrencyPrices::basketsDoneKey($masterShop, $currencyCode), 0, RecalculateMasterShopMinorCurrencyPrices::COUNTERS_TTL_SECONDS);
        Cache::put(RecalculateMasterShopMinorCurrencyPrices::basketsRemainingKey($masterShop, $currencyCode), $orderIDs->count(), RecalculateMasterShopMinorCurrencyPrices::COUNTERS_TTL_SECONDS);

        // RecalculateTotalsOrdersInBasket defaults to the 'urgent' queue, which is sized for
        // latency-sensitive customer work. A currency recalculation reprices every open basket in
        // the affected shops - tens of thousands of jobs - so sending them to urgent parks real
        // urgent work behind the backfill. sales_slave is where the action puts its own bulk
        // cascade. Baskets now finish later, so the progress bar sits in repricing_baskets longer.
        foreach ($orderIDs as $orderID) {
            RecalculateTotalsOrdersInBasket::dispatch($orderID, null, $masterShop->id, $currencyCode)
                ->onQueue('sales_slave');
        }

        if ($userID) {
            Auth::guard('web')->logout();
        }
    }
}
