<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockBarcodes
{
    use AsObject;

    /**
     * Read only: barcodes are edited from an organisation's SKO, which writes them back to this stock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Stock $stock): array
    {
        $card = fn (string $level, string $label, ?string $number, ?float $weight = null) => [
            'level'      => $level,
            'label'      => $label,
            'number'     => $number,
            'quantity'   => null,
            'weight'     => $weight,
            'dimensions' => null,
            'packs'      => null,
            'editable'   => false,
            'warning'    => null,
        ];

        return [
            $card('sko', 'SKO (outer packing, CODE 128)', $stock->barcode, $stock->gross_weight),
            $card('unit', 'Unit EAN13', $stock->unit_barcode),
            $card('carton', 'Carton (supplier outer box, same in every organisation)', $stock->carton_barcode),
        ];
    }
}
