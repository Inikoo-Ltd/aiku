<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 16 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockBarcodes
{
    use AsObject;

    public function handle(OrgStock $orgStock): array
    {
        $baseNumber = $orgStock->tradeUnits->first()?->barcode;

        if (blank($baseNumber)) {
            return [];
        }

        $stock          = $orgStock->stock;
        $unitsPerPack   = $stock?->units_per_pack;
        $unitsPerCarton = $stock?->units_per_carton;

        $barcodes = [
            [
                'level'    => 'unit',
                'number'   => $baseNumber,
                'quantity' => 1,
                'packs'    => null,
            ],
        ];

        if ($unitsPerPack) {
            $barcodes[] = [
                'level'    => 'outer',
                'number'   => $baseNumber.'N',
                'quantity' => $unitsPerPack,
                'packs'    => null,
            ];
        }

        if ($unitsPerCarton) {
            $barcodes[] = [
                'level'    => 'carton',
                'number'   => $baseNumber.'C',
                'quantity' => $unitsPerCarton,
                'packs'    => $unitsPerPack > 1 ? intdiv($unitsPerCarton, $unitsPerPack) : null,
            ];
        }

        return $barcodes;
    }
}
