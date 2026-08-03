<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\AssetTimeSeries;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\AssetTimeSeries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairAssetTimeSeriesShopId
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:asset_time_series_shop_id';

    public function asCommand(Command $command): void
    {
        $pending = AssetTimeSeries::whereNull('shop_id')->count();
        $command->info("asset_time_series with null shop_id: {$pending}");

        if ($pending === 0) {
            return;
        }

        $sql = <<<SQL
UPDATE asset_time_series
SET shop_id = assets.shop_id
FROM assets
WHERE asset_time_series.asset_id = assets.id
  AND asset_time_series.shop_id IS NULL
  AND assets.shop_id IS NOT NULL
SQL;

        $updated = DB::affectingStatement($sql);
        $command->info("updated asset_time_series: {$updated}");

        $remaining = AssetTimeSeries::whereNull('shop_id')->count();
        $command->info("remaining with null shop_id: {$remaining}");
    }
}
