<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Dec 2025 12:24:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateGrossWeightFromTradeUnits;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateHealthAndSafetyFromTradeUnits;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMarketingWeightFromTradeUnits;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;

trait WithMasterAssetTradeUnits
{
    public function processTradeUnits(MasterAsset $masterAsset, array $tradeUnitsRaw): void
    {
        $stocks     = [];
        $tradeUnits = [];
        foreach ($tradeUnitsRaw as $item) {
            $tradeUnit                  = TradeUnit::find(Arr::get($item, 'id'));
            $tradeUnits[$tradeUnit->id] = [
                'quantity' => Arr::get($item, 'quantity')
            ];

            foreach ($tradeUnit->stocks as $stock) {
                $stocks[$stock->id] = [
                    'quantity' => Arr::get($item, 'quantity') / $stock->pivot->quantity,
                ];
            }
        }

        $masterAsset->tradeUnits()->sync($tradeUnits);
        $masterAsset->stocks()->sync($stocks);

        MasterAssetHydrateHealthAndSafetyFromTradeUnits::run($masterAsset);
        MasterAssetHydrateMarketingWeightFromTradeUnits::run($masterAsset->id);
        MasterAssetHydrateGrossWeightFromTradeUnits::run($masterAsset->id);

        $masterAsset->refresh();
    }
}
