<?php

/*
 * Author Louis Perez
 * Created on 29-06-2026-13h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Barcode;

use App\Actions\Catalogue\Product\Hydrators\ProductsHydrateBarcodeFromTradeUnit;
use App\Actions\OrgAction;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\Inventory\OrgStock;

class SyncBarcodeToTradeUnit extends OrgAction
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

        if ($previousActiveTradeUnit && $previousActiveTradeUnit->id !== $newTradeUnit?->id) {
            $previousActiveTradeUnit->updateQuietly([
                'barcode_id'    => null,
                'barcode'       => null
            ]);
        }

        $barcode->update([
            'status'      => $newTradeUnit ? BarcodeStatusEnum::USED : BarcodeStatusEnum::AVAILABLE,
            'assigned_at' => $newTradeUnit ? now() : null,
        ]);

        if ($previousActiveTradeUnit && $previousActiveTradeUnit->id !== $newTradeUnit?->id) {
            $this->refreshOrgStockUnitBarcodes($previousActiveTradeUnit);
            ProductsHydrateBarcodeFromTradeUnit::dispatch($previousActiveTradeUnit);
        }
        if ($newTradeUnit) {
            $this->refreshOrgStockUnitBarcodes($newTradeUnit);
            ProductsHydrateBarcodeFromTradeUnit::dispatch($newTradeUnit);
        }

        return $barcode;
    }

    private function refreshOrgStockUnitBarcodes(TradeUnit $tradeUnit): void
    {
        OrgStock::where('is_single_trade_unit', true)
            ->whereHas('tradeUnits', fn ($query) => $query->where('trade_units.id', $tradeUnit->id))
            ->update(['unit_barcode' => $tradeUnit->barcode]);
    }

    public function action(Barcode $barcode, ?TradeUnit $newTradeUnit = null): Barcode
    {
        $this->initialisationFromGroup($barcode->group, []);

        return $this->handle($barcode, $newTradeUnit);
    }
}
