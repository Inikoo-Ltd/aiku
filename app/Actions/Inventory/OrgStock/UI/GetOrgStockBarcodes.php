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

        return [
            [
                'level'    => 'unit',
                'number'   => $baseNumber,
                'quantity' => 1,
                'packs'    => null,
            ],
        ];
    }
}
