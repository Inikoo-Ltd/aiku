<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\MasterAssetTaxPresetProgressEvent;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Progress of the basket sweep a tax preset change triggers, broadcast over websockets the
 * same way the price exchange recalculations are, so whoever pressed Food watches the
 * baskets advance instead of wondering whether anything happened.
 *
 * Progress is measured from the data, never from job completions: a basket counts as done
 * when none of its lines still point at a stale historic. The reprice job is unique per
 * order, so overlapping sweeps silently drop dispatches, and a job that dies past its
 * attempts never reports - a decrementing counter wedges on both. The state of the baskets
 * cannot lie.
 */
class TaxPresetBasketProgress
{
    public const int TTL_SECONDS = 3600;

    public static function progressKey(int $masterAssetId): string
    {
        return "master-asset:{$masterAssetId}:tax-preset-baskets";
    }

    /** @return array{state: string, baskets_done: int, baskets_total: int, started_at: string}|null */
    public static function get(MasterAsset $masterAsset): ?array
    {
        return Cache::get(static::progressKey($masterAsset->id));
    }

    /**
     * @param  array<int, int>  $orderIds
     * @param  array<int, int>  $assetIds
     */
    public static function start(MasterAsset $masterAsset, array $orderIds, array $assetIds): void
    {
        $progress = [
            'state'         => $orderIds ? 'repricing_baskets' : 'finished',
            'baskets_done'  => 0,
            'baskets_total' => count($orderIds),
            'started_at'    => now()->toIso8601String(),
        ];

        Cache::put(static::progressKey($masterAsset->id), $progress, static::TTL_SECONDS);
        Cache::put(static::progressKey($masterAsset->id).':scope', [
            'order_ids' => $orderIds,
            'asset_ids' => $assetIds,
        ], static::TTL_SECONDS);

        MasterAssetTaxPresetProgressEvent::dispatch($masterAsset, $progress);
    }

    public static function advance(int $masterAssetId): void
    {
        $masterAsset = MasterAsset::find($masterAssetId);
        $progress    = Cache::get(static::progressKey($masterAssetId));
        $scope       = Cache::get(static::progressKey($masterAssetId).':scope');
        if (!$masterAsset || !$progress || !$scope || $progress['state'] == 'finished') {
            return;
        }

        /**
         * A basket is pending while any of its swept lines still carries a historic that is
         * not the product's current one. Submitted or deleted orders drop out of the count:
         * they are no longer the sweep's to touch.
         */
        $pendingOrderIds = DB::table('orders')
            ->whereIn('orders.id', $scope['order_ids'])
            ->where('orders.state', OrderStateEnum::CREATING)
            ->whereNull('orders.deleted_at')
            ->whereExists(function ($query) use ($scope) {
                $query->selectRaw('1')
                    ->from('transactions')
                    ->join('products', 'products.asset_id', 'transactions.asset_id')
                    ->whereColumn('transactions.order_id', 'orders.id')
                    ->whereNull('transactions.deleted_at')
                    ->whereIn('transactions.asset_id', $scope['asset_ids'])
                    ->whereColumn('transactions.historic_asset_id', '!=', 'products.current_historic_asset_id');
            })
            ->pluck('orders.id');

        $pending = $pendingOrderIds->count();

        /**
         * A giant basket recalculates for minutes on the long-running lane and the bar sits
         * one short of done meanwhile - it reads as stuck unless it says why.
         */
        $pendingLarge = $pending == 0 ? 0 : DB::table('transactions')
            ->whereIn('order_id', $pendingOrderIds)
            ->whereNull('deleted_at')
            ->groupBy('order_id')
            ->havingRaw('count(*) > 250')
            ->pluck('order_id')
            ->count();

        $done = max(0, $progress['baskets_total'] - $pending);

        if ($done == $progress['baskets_done'] && $pending > 0 && ($progress['pending_large'] ?? null) === $pendingLarge) {
            return;
        }

        $progress['baskets_done']  = $done;
        $progress['pending_large'] = $pendingLarge;
        $progress['state']         = $pending <= 0 ? 'finished' : 'repricing_baskets';

        Cache::put(static::progressKey($masterAssetId), $progress, static::TTL_SECONDS);
        MasterAssetTaxPresetProgressEvent::dispatch($masterAsset, $progress);

        if ($pending <= 0) {
            Cache::forget(static::progressKey($masterAssetId).':scope');
        }
    }
}
