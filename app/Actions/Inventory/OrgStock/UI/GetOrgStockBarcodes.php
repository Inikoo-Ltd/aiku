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

    /**
     * The unit card is always returned, with a null number when the org stock has no barcode yet,
     * so the page can offer the placeholder that lets warehouse staff type or scan one in.
     */
    public function handle(OrgStock $orgStock): array
    {
        $tradeUnit = $orgStock->tradeUnits->first();

        return [
            [
                'level'      => 'unit',
                'label'      => 'Unit',
                'number'     => $orgStock->barcode,
                'quantity'   => 1,
                'weight'     => $tradeUnit?->marketing_weight,
                'dimensions' => $tradeUnit?->marketing_dimensions,
                'packs'      => null,
            ],
        ];
    }
}
