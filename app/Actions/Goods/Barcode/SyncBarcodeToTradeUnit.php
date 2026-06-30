<?php

/*
 * Author Louis Perez
 * Created on 29-06-2026-13h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Barcode;

use App\Actions\Catalogue\Product\Hydrators\ProductsHydrateBarcodeFromTradeUnit;
use App\Actions\GrpAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;

class SyncBarcodeToTradeUnit extends GrpAction
{
    public function handle(Barcode $barcode, ?TradeUnit $newTradeUnit = null): Barcode
    {
        $previousActiveTradeUnit = $barcode->tradeUnitsActive->first();

        $tradeUnits = $barcode->tradeUnits->mapWithKeys(fn ($item) => [
            $item['id'] => [
                'type'          => $barcode->type,
                'status'        => false,
                'withdrawn_at'  => now()
            ]
        ])->toArray();

        if ($newTradeUnit) {
            data_set($tradeUnits, $newTradeUnit->id, [
                'type'          => $barcode->type,
                'status'        => true,
                'withdrawn_at'  => null
            ]);

            $newTradeUnit->updateQuietly([
                'barcode_id'    => $barcode->id,
                'barcode'       => $barcode->number
            ]);
        }

        $barcode->tradeUnits()->sync($tradeUnits);

        // Remove previously active trade unit barcode to be null
        if ($previousActiveTradeUnit) {
            $previousActiveTradeUnit->updateQuietly([
                'barcode_id'    => null,
                'barcode'       => null
            ]);
        }

        // Hydrate old trade units product. Would set their barcode to null
        ProductsHydrateBarcodeFromTradeUnit::dispatch($previousActiveTradeUnit);
        if ($newTradeUnit) {
            // Hydrate new trade units product. Would set their barcode
            ProductsHydrateBarcodeFromTradeUnit::dispatch($newTradeUnit);
        }

        return $barcode;
    }

    public function action(Barcode $barcode, ?TradeUnit $newTradeUnit = null): Barcode
    {
        $this->initialisation($barcode->group, []);

        return $this->handle($barcode, $newTradeUnit);
    }
}
