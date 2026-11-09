<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 01:59:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\Catalogue\Product;
use App\Models\Goods\Stock;
use App\Models\Masters\MasterAsset;

trait WithWeightFromTradeUnits
{
    public function getWeightFromTradeUnits(Stock|Product|MasterAsset $model): ?int
    {
        $changed = false;
        $weight  = 0;

        foreach ($model->tradeUnits as $tradeUnit) {
            if (is_numeric($tradeUnit->gross_weight) && is_numeric($tradeUnit->pivot->quantity)) {
                $changed = true;
                $weight  += $tradeUnit->gross_weight * $tradeUnit->pivot->quantity;
            }
        }

        if (!$changed) {
            $weight = null;
        } else {
            $weight = (int)ceil($weight);
        }


        return $weight;
    }

    /**
     * A product built from several trade units, a bundle above all, weighs what its parts weigh
     * together, so its marketing weight is summed the way the gross weight already is. Taking a
     * single trade unit only makes sense when there is only one.
     */
    public function getMarketingWeightFromTradeUnits(Stock|Product|MasterAsset $model): ?int
    {
        $changed = false;
        $weight  = 0;

        foreach ($model->tradeUnits as $tradeUnit) {
            if (is_numeric($tradeUnit->marketing_weight) && is_numeric($tradeUnit->pivot->quantity)) {
                $changed = true;
                $weight  += $tradeUnit->marketing_weight * $tradeUnit->pivot->quantity;
            }
        }

        return $changed ? (int)ceil($weight) : null;
    }

    /**
     * Net weight is shown per unit ("120 g/Slice"). A product sold as one unit, or as a fraction
     * of one (units up to 1), weighs its share of the trade unit: a slice that is 0.1 of a 1.2 kg
     * loaf weighs 120 g, a pack of 10 bottles of 39 g weighs 390 g. A product of several units
     * keeps the trade unit's weight, each unit being a trade unit. A quantity of 0.9 or more is a
     * yield allowance (a 500 g candle at 0.96), not a fraction, so it counts as a whole one.
     */
    public static function getSingleTradeUnitMarketingWeight(?int $tradeUnitWeight, float|string|null $quantity, float|string|null $units): ?int
    {
        if ($tradeUnitWeight === null || !is_numeric($quantity)) {
            return $tradeUnitWeight;
        }

        $quantity = (float)$quantity;
        $units    = is_numeric($units) ? (float)$units : 1.0;

        if ($quantity <= 0 || $units > 1) {
            return $tradeUnitWeight;
        }

        if ($quantity >= 0.9 && $quantity < 1) {
            $quantity = 1.0;
        }

        return (int)round($tradeUnitWeight * $quantity);
    }
}
