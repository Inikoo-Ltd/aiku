<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 01:59:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\Catalogue\Product;
use App\Models\Goods\Stock;

trait WithWeightFromTradeUnits
{
    public function getWeightFromTradeUnits(Stock|Product $model): ?int
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
}
