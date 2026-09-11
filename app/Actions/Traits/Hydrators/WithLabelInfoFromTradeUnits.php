<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-11h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Traits\Hydrators;

use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

trait WithLabelInfoFromTradeUnits
{
    public function getLabelPresenceFromTradeUnits(Product|MasterAsset $model): array
    {
        $labelPresence = [];

        foreach (TradeUnitLabelPresenceEnum::values() as $field) {
            $labelPresence[$field] = $model->tradeUnits->contains(
                fn ($tradeUnit) => (bool) data_get($tradeUnit->label_info, $field, false)
            );
        }

        return $labelPresence;
    }

    public function mergeLabelPresence(Product|MasterAsset $model, array $labelPresence): array
    {
        return array_merge($model->label_info ?? [], $labelPresence);
    }
}
