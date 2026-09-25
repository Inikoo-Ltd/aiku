<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;

/**
 * A product built from one trade unit mirrors its barcode. Built from several it has no
 * automatic answer, and publishing a member's barcode is how eight lamps came to share the
 * fitting's EAN. So the options are shown with the trade unit they belong to and a person
 * picks; the ranking below only decides which one is suggested.
 */
trait WithBarcodeChoice
{
    /**
     * @return array{options: list<array{value: string, code: string, name: string, proposed: bool}>, withoutBarcode: list<array{code: string, name: string}>, hasChoice: bool}
     */
    public function getBarcodeChoice(MasterAsset|Product $model): array
    {
        $withoutBarcode = $model->tradeUnits
            ->unique('id')
            ->filter(fn (TradeUnit $tradeUnit) => blank($tradeUnit->barcode))
            ->map(fn (TradeUnit $tradeUnit) => [
                'code' => $tradeUnit->code,
                'name' => $tradeUnit->name,
            ])
            ->values()
            ->all();

        $tradeUnits = $model->tradeUnits
            ->unique('id')
            ->filter(fn (TradeUnit $tradeUnit) => filled($tradeUnit->barcode))
            /*
             * ponytail: the accessory is the member reused across the catalogue - the cap on
             * 306 masters, the fitting on 187 - so the rarest member is the product. Read from
             * the already hydrated stats, no new flag and no extra query. A hint on a screen
             * where a person confirms every time, never applied on its own.
             */
            ->sortBy(fn (TradeUnit $tradeUnit) => $tradeUnit->stats?->number_products ?? 0)
            ->values();

        $isUnambiguous = $tradeUnits->count() > 1
            && ($tradeUnits[0]->stats?->number_products ?? 0) < ($tradeUnits[1]->stats?->number_products ?? 0);

        return [
            'options'   => $tradeUnits->map(fn (TradeUnit $tradeUnit, int $index) => [
                'value'    => (string)$tradeUnit->barcode,
                'code'     => $tradeUnit->code,
                'name'     => $tradeUnit->name,
                'proposed' => $index == 0 && $isUnambiguous,
            ])->all(),
            'withoutBarcode' => $withoutBarcode,
            'hasChoice' => $model->tradeUnits->unique('id')->count() > 1,
        ];
    }
}
