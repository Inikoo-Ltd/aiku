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
     * Both cards are always returned, with a null number when the org stock has no barcode yet, so
     * the page can offer the placeholder that lets warehouse staff type or scan one in.
     *
     * The unit card's number is editable only when the org stock is a single trade unit, because it
     * is the trade unit's EAN13 and editing it cascades to the product barcode everywhere it sells.
     */
    public function handle(OrgStock $orgStock): array
    {
        $tradeUnit = $orgStock->is_single_trade_unit ? $orgStock->tradeUnits->first() : null;

        $skoWeight = $orgStock->stock?->gross_weight
            ?? (($orgStock->packed_in && $tradeUnit?->gross_weight) ? $orgStock->packed_in * $tradeUnit->gross_weight : null);

        return [
            [
                'level'      => 'sko',
                'label'      => 'SKO (outer packing, CODE 128)',
                'number'     => $orgStock->barcode,
                'quantity'   => $orgStock->packed_in,
                'weight'     => $skoWeight,
                'dimensions' => null,
                'packs'      => null,
                'editable'   => true,
            ],
            [
                'level'      => 'unit',
                'label'      => 'Unit EAN13',
                'number'     => $orgStock->unit_barcode,
                'quantity'   => 1,
                'weight'     => $tradeUnit?->marketing_weight,
                'dimensions' => $tradeUnit?->marketing_dimensions,
                'packs'      => null,
                'editable'   => (bool) $tradeUnit,
            ],
        ];
    }
}
