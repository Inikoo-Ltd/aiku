<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RecalculateMasterShopMinorCurrencyPricesChunk
{
    use AsAction;

    public string $jobQueue = 'price_change';

    /**
     * @param array<int, int> $assetIDs
     * @throws Throwable
     */
    public function handle(MasterShop $masterShop, string $currencyCode, array $assetIDs, string $majorCurrencyCode, float $exchange, ?int $userID = null): void
    {
        $affectedShops  = RecalculateMasterShopMinorCurrencyPrices::getAffectedShops($masterShop, $currencyCode);
        $fractionDigits = (int)data_get($masterShop->price_exchanges, "$currencyCode.fraction_digits", 2);

        try {
            if ($userID) {
                Auth::guard('web')->loginUsingId($userID);
            }
            Product::withoutSyncingToSearch(function () use ($assetIDs, $currencyCode, $majorCurrencyCode, $exchange, $affectedShops, $fractionDigits) {
                foreach (MasterAsset::whereIn('id', $assetIDs)->get() as $masterAsset) {
                    $this->recalculateMasterAsset($masterAsset, $currencyCode, $majorCurrencyCode, $exchange, $affectedShops, $fractionDigits);
                }
            });
        } finally {
            if ($userID) {
                Auth::guard('web')->logout();
            }
        }

        $done     = (int)Cache::increment(RecalculateMasterShopMinorCurrencyPrices::doneKey($masterShop, $currencyCode), count($assetIDs));
        $progress = RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode);

        if ($progress && $progress['state'] === 'updating_prices') {
            RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, array_merge($progress, [
                'done' => min($done, $progress['total']),
            ]));
        }

        if (Cache::add(RecalculateMasterShopMinorCurrencyPrices::cacheBreakThrottleKey($masterShop, $currencyCode), 1, 60)) {
            RecalculateMasterShopMinorCurrencyPrices::breakWebsitesCache(
                RecalculateMasterShopMinorCurrencyPrices::getAffectedShops($masterShop, $currencyCode),
                null
            );
        }

        $remaining = Cache::decrement(RecalculateMasterShopMinorCurrencyPrices::remainingChunksKey($masterShop, $currencyCode));
        if ($remaining <= 0
            && Cache::add(RecalculateMasterShopMinorCurrencyPrices::progressKey($masterShop, $currencyCode).':finalising', 1, 3600)
        ) {
            FinaliseRecalculateMasterShopMinorCurrencyPrices::dispatch($masterShop, $currencyCode, $userID);
        }
    }

    public function jobFailed(Throwable $e, MasterShop $masterShop, string $currencyCode): void
    {
        RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, [
            'state' => 'failed',
            'done'  => (int)Cache::get(RecalculateMasterShopMinorCurrencyPrices::doneKey($masterShop, $currencyCode)),
            'total' => (int)data_get(RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode), 'total'),
            'error' => $e->getMessage(),
        ]);

        $remaining = Cache::decrement(RecalculateMasterShopMinorCurrencyPrices::remainingChunksKey($masterShop, $currencyCode));
        if ($remaining <= 0
            && Cache::add(RecalculateMasterShopMinorCurrencyPrices::progressKey($masterShop, $currencyCode).':finalising', 1, 3600)
        ) {
            FinaliseRecalculateMasterShopMinorCurrencyPrices::dispatch($masterShop, $currencyCode);
        }
    }

    protected function recalculateMasterAsset(MasterAsset $masterAsset, string $currencyCode, string $majorCurrencyCode, float $exchange, Collection $affectedShops, int $fractionDigits): void
    {
        $masterAsset = DB::transaction(function () use ($masterAsset, $currencyCode, $majorCurrencyCode, $exchange, $fractionDigits) {
            $masterAsset = MasterAsset::lockForUpdate()->find($masterAsset->id);
            if ($masterAsset) {
                $this->applyExchange($masterAsset, $currencyCode, $majorCurrencyCode, $exchange, $fractionDigits);
            }

            return $masterAsset;
        });

        if ($masterAsset && data_get($masterAsset->master_prices, "$currencyCode.value")) {
            foreach ($affectedShops as $shop) {
                MasterAssetHydrateMasterPricesRRPtoChild::run($masterAsset, $shop, bulkPriceUpdate: true);
            }
        }
    }

    protected function applyExchange(MasterAsset $masterAsset, string $currencyCode, string $majorCurrencyCode, float $exchange, int $fractionDigits = 2): bool
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

            $newValue = formatPrice($majorValue, $exchange, $field === 'master_prices' ? $fractionDigits : 2);
            if ((float)$newValue <= 0 || (string)data_get($values, "$currencyCode.value") === $newValue) {
                continue;
            }
            if ((float)$newValue >= 100000000) {
                Log::warning("Price exchange skip $field $currencyCode for master asset $masterAsset->code: value $newValue too big, source data looks corrupt");
                continue;
            }

            data_set($values, "$currencyCode.value", $newValue);
            $modelData[$field] = $values;
        }

        if (!$modelData) {
            return false;
        }

        $masterAsset->updateQuietly($modelData);

        return true;
    }
}
