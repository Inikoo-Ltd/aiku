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
        $tradeUnit = $orgStock->tradeUnits->first();
        $baseNumber = $tradeUnit?->barcode;
        $unitWeight = $tradeUnit?->marketing_weight;
        $unitDimensions = $tradeUnit?->marketing_dimensions;

        if (blank($baseNumber)) {
            return [];
        }

        return [
            [
                'level'      => 'unit',
                'label'      => 'Unit',
                'number'     => $baseNumber,
                'quantity'   => 1,
                'weight'     => $unitWeight,
                'dimensions' => $unitDimensions,
                'packs'      => null,
            ],
        ];
    }
}
