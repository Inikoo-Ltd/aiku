<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Traits\Hydrators;

use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

trait WithLabelInfoFromTradeUnits
{
    public function getLabelInfoFromTradeUnits(Product|MasterAsset $model): array
    {
        $tradeUnits = $model->tradeUnits()->get();

        $labelInfo = [];

        foreach (TradeUnitLabelPresenceEnum::values() as $field) {
            $labelInfo[$field] = $tradeUnits->contains(
                fn ($tradeUnit) => (bool) data_get($tradeUnit->label_info, $field, false)
            );
        }

        $labelInfo['markets'] = $tradeUnits->isEmpty()
            ? []
            : array_values(array_filter(
                TradeUnitMarketEnum::values(),
                fn ($market) => $tradeUnits->every(fn ($tradeUnit) => in_array($market, (array) data_get($tradeUnit->label_info, 'markets', []), true))
            ));

        $labelInfo['languages'] = $tradeUnits
            ->flatMap(fn ($tradeUnit) => (array) data_get($tradeUnit->label_info, 'languages', []))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $bestBefores = $tradeUnits->map(fn ($tradeUnit) => data_get($tradeUnit->label_info, 'best_before'))->unique();

        $labelInfo['best_before'] = $bestBefores->count() === 1 ? $bestBefores->first() : null;

        $shownPackagingTradeUnits = $tradeUnits->filter(
            fn ($tradeUnit) => data_get($tradeUnit->label_info, 'packaging_material_codes.show', false) === true
        );

        $labelInfo['packaging_material_codes'] = [
            'show'  => $shownPackagingTradeUnits->isNotEmpty(),
            'value' => array_values(array_filter(
                TradeUnitPackagingMaterialEnum::values(),
                fn ($code) => $shownPackagingTradeUnits->contains(
                    fn ($tradeUnit) => in_array($code, (array) data_get($tradeUnit->label_info, 'packaging_material_codes.value', []), true)
                )
            )),
        ];

        return $labelInfo;
    }

    public function getLabelInfoFromMaster(MasterAsset $masterAsset): array
    {
        $masterLabelInfo = $masterAsset->label_info ?? [];

        $labelInfo = [];

        foreach (TradeUnitLabelPresenceEnum::values() as $field) {
            $labelInfo[$field] = (bool) data_get($masterLabelInfo, $field, false);
        }

        $labelInfo['markets']   = (array) data_get($masterLabelInfo, 'markets', []);
        $labelInfo['languages'] = (array) data_get($masterLabelInfo, 'languages', []);

        $labelInfo['best_before'] = data_get($masterLabelInfo, 'best_before');

        $labelInfo['packaging_material_codes'] = [
            'show'  => data_get($masterLabelInfo, 'packaging_material_codes.show', false) === true,
            'value' => (array) data_get($masterLabelInfo, 'packaging_material_codes.value', []),
        ];

        return $labelInfo;
    }

    public function mergeLabelInfo(Product|MasterAsset $model, array $labelInfo): array
    {
        return array_merge($model->label_info ?? [], $labelInfo);
    }
}
