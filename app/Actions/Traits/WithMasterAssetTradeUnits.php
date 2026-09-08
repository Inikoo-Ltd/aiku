<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Dec 2025 12:24:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingIngredientsFromTradeUnits;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateGrossWeightFromTradeUnits;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateHealthAndSafetyFromTradeUnits;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMarketingWeightFromTradeUnits;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;

trait WithMasterAssetTradeUnits
{
    /**
     * How the composition editor should offer units. A single trade unit states its own pack
     * size, so there is nothing to show. Several that disagree cannot imply one, so units are
     * typed by hand with nothing to opt out of. Several that agree do imply one, and that is
     * the only case where opting out is a choice.
     *
     * @return array{shown: bool, canToggle: bool, byHand: bool}
     */
    public function getUnitsEditability(MasterAsset|Product $model): array
    {
        $tradeUnits = $model->tradeUnits;

        if ($tradeUnits->count() < 2) {
            return ['shown' => false, 'canToggle' => false, 'byHand' => false];
        }

        $impliedUnits = $this->getSharedTradeUnitQuantity(
            $tradeUnits->map(fn ($tradeUnit) => ['quantity' => $tradeUnit->pivot->quantity])->all()
        );

        if ($impliedUnits === null) {
            return ['shown' => true, 'canToggle' => false, 'byHand' => true];
        }

        return ['shown' => true, 'canToggle' => true, 'byHand' => (bool) $model->has_independent_units];
    }

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
     * @return array{type: string, label: string, value: float, canToggle: bool, hasOther: array{name: string, value: bool}}|null
     */
    public function getUnitsField(MasterAsset|Product $model, ?array $saveConfirmation = null): ?array
    {
        $editability = $this->getUnitsEditability($model);

        if (!$editability['shown']) {
            return null;
        }

        return [
            'type'             => 'composition_units',
            'label'            => __('Units'),
            'value'            => (float) $model->units,
            'canToggle'        => $editability['canToggle'],
            /** The block owns its save button, so it can hide it while there is nothing to save. */
            'noSaveButton'     => true,
            'hasOther'         => ['name' => 'has_independent_units', 'value' => $editability['byHand']],
            'saveConfirmation' => $saveConfirmation,
        ];
    }

    /**
     * Units are null when the composition cannot imply them - trade units packing different
     * quantities - and the caller leaves whatever was set by hand alone rather than flattening
     * it to 1.
     *
     * @param array<int, array{quantity?: mixed, type?: mixed}> $tradeUnits
     *
     * @return array{units: float|null, unit: string|null}
     */
    public function getUnitsFromTradeUnits(array $tradeUnits): array
    {
        if (empty($tradeUnits)) {
            return ['units' => 1.0, 'unit' => null];
        }

        $units = $this->getSharedTradeUnitQuantity($tradeUnits);

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
        ProductHydrateMarketingIngredientsFromTradeUnits::run($masterAsset);

        $masterAsset->refresh();
    }
}
