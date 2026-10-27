<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 16 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Goods\Stock\UI\GetStockBarcodes;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockBarcodes
{
    use AsObject;

    /**
     * All three cards are always returned, with a null number when the org stock has no barcode yet, so
     * the page can offer the placeholder that lets warehouse staff type or scan one in.
     *
     * The unit card's number is editable only when the org stock is a single trade unit, because it
     * is the trade unit's EAN13 and editing it cascades to the product barcode everywhere it sells.
     *
     * The cards go from the smallest pack to the biggest. Quantity is the units each one holds, and
     * the carton's packs the SKOs in it. A carton is the supplier's box, so a supplier product page
     * passes its own units per carton; otherwise it is known only when every supplier agrees.
     */
    public function handle(OrgStock $orgStock, ?int $unitsPerCarton = null): array
    {
        $tradeUnit = $orgStock->is_single_trade_unit ? $orgStock->tradeUnits->first() : null;

        $skoWeight = $orgStock->stock?->gross_weight
            ?? (($orgStock->packed_in && $tradeUnit?->gross_weight) ? $orgStock->packed_in * $tradeUnit->gross_weight : null);

        $unitsPerCarton ??= GetStockBarcodes::unitsPerCarton($orgStock->stock);

        return [
            [
                'level'      => 'unit',
                'label'      => 'Unit EAN13',
                'number'     => $orgStock->unit_barcode,
                'quantity'   => 1,
                'weight'     => $tradeUnit?->marketing_weight,
                'dimensions' => $tradeUnit?->marketing_dimensions,
                'packs'      => null,
                'editable'   => (bool) $tradeUnit,
                'warning'    => null,
            ],
            [
                'level'      => 'sko',
                'label'      => 'SKO (outer packing, CODE 128)',
                'number'     => $orgStock->barcode,
                'quantity'   => $orgStock->packed_in ? (float) $orgStock->packed_in : null,
                'weight'     => $skoWeight,
                'dimensions' => null,
                'packs'      => null,
                'editable'   => true,
                'warning'    => static::singleUnitSkoWarning($orgStock),
            ],
            [
                'level'      => 'carton',
                'label'      => 'Carton (supplier outer box, same in every organisation)',
                'number'     => $orgStock->stock?->carton_barcode,
                'quantity'   => $unitsPerCarton,
                'weight'     => null,
                'dimensions' => null,
                'packs'      => GetStockBarcodes::skosPerCarton($unitsPerCarton, $orgStock->packed_in ? (float) $orgStock->packed_in : null),
                'editable'   => (bool) $orgStock->stock_id,
                'warning'    => null,
            ],
        ];
    }

    /**
     * An SKO that holds a single unit has no outer packing distinct from the unit, so a number in
     * this slot is the unit's own EAN sitting one level too high: it is where scanning reads it as
     * an outer and where the label printer prints it as CODE 128. Staff are not blocked, because a
     * warehouse is free to put its own CODE 128 label on a single-unit item, but the card says so.
     */
    public static function singleUnitSkoWarning(OrgStock $orgStock): ?string
    {
        if ((int)($orgStock->packed_in ?? 1) > 1) {
            return null;
        }

        return __('This SKO holds one unit, so it has no outer packing of its own.');
    }
}
