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
    /**
     * The units a composition implies: the trade unit quantity when every trade unit packs the
     * same number of them, null when they differ and no single pack size can be read off it.
     *
     * @param array<int, array{quantity?: mixed}> $tradeUnits
     */
    public function getSharedTradeUnitQuantity(array $tradeUnits): ?float
    {
        $quantities = array_unique(
            array_map(fn ($tradeUnit) => (float) Arr::get($tradeUnit, 'quantity', 1), array_values($tradeUnits))
        );

        if (count($quantities) !== 1) {
            return null;
        }

        $quantity = reset($quantities);

        return $quantity == 1 && count($tradeUnits) > 1 ? null : $quantity;
    }

    /**
     * @param array<int, array{quantity?: mixed, type?: mixed}> $tradeUnits
     *
     * @return array{units: float, unit: string|null}
     */
    public function getUnitsFromTradeUnits(array $tradeUnits): array
    {
        if (empty($tradeUnits)) {
            return ['units' => 1.0, 'unit' => null];
        }

        $units = $this->getSharedTradeUnitQuantity($tradeUnits) ?? 1.0;

        if (count($tradeUnits) === 1) {
            return ['units' => $units, 'unit' => Arr::get(array_values($tradeUnits)[0], 'type')];
        }

        return ['units' => $units, 'unit' => 'bundle'];
    }

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
        ModelHydrateSingleTradeUnits::run($masterAsset);
        MasterAssetHydrateHealthAndSafetyFromTradeUnits::run($masterAsset);
        MasterAssetHydrateMarketingWeightFromTradeUnits::run($masterAsset->id);
        MasterAssetHydrateGrossWeightFromTradeUnits::run($masterAsset->id);

        $masterAsset->refresh();
    }
}
