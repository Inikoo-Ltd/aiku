<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockBarcodes
{
    use AsObject;

    /**
     * Read only: barcodes are edited from an organisation's SKO, which writes them back to this stock.
     * The packing of an SKO lives on the organisations' SKOs, so it is shown only when they all agree.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Stock $stock, ?int $unitsPerCarton = null): array
    {
        $card = fn (string $level, string $label, ?string $number, ?float $weight = null, ?float $quantity = null, ?float $packs = null) => [
            'level'      => $level,
            'label'      => $label,
            'number'     => $number,
            'quantity'   => $quantity,
            'weight'     => $weight,
            'dimensions' => null,
            'packs'      => $packs,
            'editable'   => false,
            'warning'    => null,
        ];

        $packings       = OrgStock::where('stock_id', $stock->id)->whereNotNull('packed_in')->distinct()->pluck('packed_in');
        $skoPackedIn    = $packings->count() === 1 ? (float) $packings->first() : null;
        $unitsPerCarton ??= static::unitsPerCarton($stock);

        return [
            $card('unit', 'Unit EAN13', $stock->unit_barcode, quantity: 1),
            $card('sko', 'SKO (outer packing, CODE 128)', $stock->barcode, $stock->gross_weight, $skoPackedIn),
            $card('carton', 'Carton (supplier outer box, same in every organisation)', $stock->carton_barcode, quantity: $unitsPerCarton, packs: static::skosPerCarton($unitsPerCarton, $skoPackedIn)),
        ];
    }

    /**
     * stocks.units_per_carton is never filled; the carton is the supplier's box, so its size comes
     * from the supplier products, and only when they all agree, since two suppliers can ship it in
     * different cartons.
     */
    public static function unitsPerCarton(?Stock $stock): ?int
    {
        $unitsPerCarton = $stock?->supplierProducts
            ->pluck('units_per_carton')
            ->filter(fn ($units) => $units > 0)
            ->unique();

        return $unitsPerCarton?->count() === 1 ? (int) $unitsPerCarton->first() : null;
    }

    public static function skosPerCarton(?int $unitsPerCarton, ?float $skoPackedIn): ?float
    {
        return $unitsPerCarton && $skoPackedIn ? round($unitsPerCarton / $skoPackedIn, 2) : null;
    }
}
