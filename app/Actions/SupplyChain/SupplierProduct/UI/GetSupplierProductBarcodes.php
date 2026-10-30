<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 13:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierProductBarcodes
{
    use AsObject;

    /**
     * Read only cards for a supplier product not linked to any stock yet. The unit EAN13 is the trade
     * unit's, known only when the product is a single trade unit. The SKO and carton barcodes live on
     * the stock, so they stay empty until the product is linked to one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(SupplierProduct $supplierProduct): array
    {
        $tradeUnit = $supplierProduct->tradeUnits->count() === 1 ? $supplierProduct->tradeUnits->first() : null;

        $card = fn (string $level, string $label, ?string $number, ?float $quantity = null, ?float $weight = null, ?array $dimensions = null) => [
            'level'      => $level,
            'label'      => $label,
            'number'     => $number,
            'quantity'   => $quantity,
            'weight'     => $weight,
            'dimensions' => $dimensions,
            'packs'      => null,
            'editable'   => false,
            'warning'    => null,
        ];

        return [
            $card('unit', 'Unit EAN13', $tradeUnit?->barcode, 1, $tradeUnit?->marketing_weight, $tradeUnit?->marketing_dimensions),
            $card('sko', 'SKO (outer packing, CODE 128)', null, $supplierProduct->units_per_pack ? (float) $supplierProduct->units_per_pack : null),
            $card('carton', 'Carton (supplier outer box, same in every organisation)', null, $supplierProduct->units_per_carton ? (float) $supplierProduct->units_per_carton : null),
        ];
    }
}
