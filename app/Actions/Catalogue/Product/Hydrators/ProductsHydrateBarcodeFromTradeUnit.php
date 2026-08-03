<?php

/*
 * Author Louis Perez
 * Created on 29-06-2026-13h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Models\Goods\TradeUnit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductsHydrateBarcodeFromTradeUnit implements ShouldBeUnique
{
    use AsAction;
    use WithWeightFromTradeUnits;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }

    public function handle(TradeUnit $tradeUnit): void
    {
        foreach ($tradeUnit->products as $product) {
            ProductHydrateBarcodeFromTradeUnit::run($product);
        }
    }

}
