<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Web\Website\BreakWebsiteCache;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Events\MasterShopPriceExchangeProgressEvent;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateMasterShopMinorCurrencyPrices
{
    use AsAction;

    public string $jobQueue = 'price_change_control';

    public const int PROGRESS_TTL_SECONDS = 7200;
    public const int COUNTERS_TTL_SECONDS = 28800;
    public const int CHUNK_SIZE = 10;
    public const int NUMBER_SINGLE_ASSET_CHUNKS = 5;

    public static function progressKey(MasterShop $masterShop, string $currencyCode): string
    {
        return "master_shop:$masterShop->id:price_exchange_progress:$currencyCode";
    }

    public static function doneKey(MasterShop $masterShop, string $currencyCode): string
    {
        return static::progressKey($masterShop, $currencyCode).':done';
    }

    public static function remainingChunksKey(MasterShop $masterShop, string $currencyCode): string
    {
        return static::progressKey($masterShop, $currencyCode).':remaining_chunks';
    }

    public static function basketsDoneKey(MasterShop $masterShop, string $currencyCode): string
    {
        return static::progressKey($masterShop, $currencyCode).':baskets_done';
    }

    public static function basketsRemainingKey(MasterShop $masterShop, string $currencyCode): string
    {
        return static::progressKey($masterShop, $currencyCode).':baskets_remaining';
    }

    public static function cacheBreakThrottleKey(MasterShop $masterShop, string $currencyCode): string
    {
        return static::progressKey($masterShop, $currencyCode).':cache_break_throttle';
    }

    /** @return array{state: string, done: int, total: int, baskets_total?: int, started_at: string, error?: string}|null */
    public static function getProgress(MasterShop $masterShop, string $currencyCode): ?array
    {
        return Cache::get(static::progressKey($masterShop, $currencyCode));
    }

    public static function setProgress(MasterShop $masterShop, string $currencyCode, array $progress): array
    {
        $progress['started_at'] ??= now()->toIso8601String();
        Cache::put(static::progressKey($masterShop, $currencyCode), $progress, static::PROGRESS_TTL_SECONDS);
        MasterShopPriceExchangeProgressEvent::dispatch($masterShop, $currencyCode, $progress);

        return $progress;
    }

    public static function forgetProgress(MasterShop $masterShop, string $currencyCode): void
    {
        Cache::forget(static::progressKey($masterShop, $currencyCode));
        Cache::forget(static::doneKey($masterShop, $currencyCode));
        Cache::forget(static::remainingChunksKey($masterShop, $currencyCode));
        Cache::forget(static::progressKey($masterShop, $currencyCode).':finalising');
        Cache::forget(static::basketsDoneKey($masterShop, $currencyCode));
        Cache::forget(static::basketsRemainingKey($masterShop, $currencyCode));
        Cache::forget(static::cacheBreakThrottleKey($masterShop, $currencyCode));
    }

    public static function getAffectedShops(MasterShop $masterShop, string $currencyCode): Collection
    {
        return $masterShop->shops()
            ->where('state', ShopStateEnum::OPEN)
            ->whereHas('currency', fn ($query) => $query->where('code', $currencyCode))
            ->get();
    }

    public static function breakWebsitesCache(Collection $affectedShops, ?CrawlTriggerEnum $crawlTrigger): void
    {
        foreach ($affectedShops as $shop) {
            if ($shop->website) {
                BreakWebsiteCache::run($shop->website, $crawlTrigger);
            }
        }
    }

    public function handle(MasterShop $masterShop, string $currencyCode, ?int $userID = null): void
    {
        $exchangeData = data_get($masterShop->price_exchanges, $currencyCode);

        $majorCurrencyCode = $exchangeData['major'] ?? null;
        $exchange          = $exchangeData['exchange'] ?? null;

        if (!$exchangeData || ($exchangeData['is_major'] ?? false) || !$majorCurrencyCode || !$exchange) {
            static::forgetProgress($masterShop, $currencyCode);

            return;
        }

        $activeCurrencies = collect(array_keys($masterShop->price_exchanges ?? []))
            ->filter(function (string $otherCurrencyCode) use ($masterShop, $currencyCode) {
                if ($otherCurrencyCode === $currencyCode) {
                    return false;
                }
                $otherProgress = static::getProgress($masterShop, $otherCurrencyCode);

                return $otherProgress && in_array($otherProgress['state'], ['queued', 'updating_prices']);
            });

        if ($activeCurrencies->isNotEmpty()) {
            static::setProgress($masterShop, $currencyCode, [
                'state'       => 'waiting',
                'done'        => 0,
                'total'       => 0,
                'waiting_for' => $activeCurrencies->values()->all(),
                'started_at'  => data_get(static::getProgress($masterShop, $currencyCode), 'started_at'),
            ]);
            static::dispatch($masterShop, $currencyCode, $userID)->delay(15);

            return;
        }

        $assetIDs = $masterShop->masterAssets()
            ->where('is_main', true)
            ->where('status', true)
            ->pluck('id');

        static::setProgress($masterShop, $currencyCode, [
            'state'               => 'updating_prices',
            'done'                => 0,
            'total'               => $assetIDs->count(),
            'started_at'          => data_get(static::getProgress($masterShop, $currencyCode), 'started_at'),
            'updating_started_at' => now()->toIso8601String(),
        ]);

        $singles = $assetIDs->take(static::NUMBER_SINGLE_ASSET_CHUNKS)->chunk(1);
        $chunks  = $singles->concat(
            $assetIDs->slice(static::NUMBER_SINGLE_ASSET_CHUNKS)->chunk(static::CHUNK_SIZE)
        );

        if ($chunks->isEmpty()) {
            FinaliseRecalculateMasterShopMinorCurrencyPrices::dispatch($masterShop, $currencyCode, $userID);

            return;
        }

        Cache::put(static::doneKey($masterShop, $currencyCode), 0, static::COUNTERS_TTL_SECONDS);
        Cache::put(static::remainingChunksKey($masterShop, $currencyCode), $chunks->count(), static::COUNTERS_TTL_SECONDS);

        foreach ($chunks as $chunk) {
            RecalculateMasterShopMinorCurrencyPricesChunk::dispatch(
                $masterShop,
                $currencyCode,
                $chunk->values()->all(),
                $majorCurrencyCode,
                (float)$exchange,
                $userID
            );
        }
    }
}
