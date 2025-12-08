<?php

/*
 * author Arya Permana - Kirin
 * created on 28-02-2025-08h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Exports\Pallets;

use Maatwebsite\Excel\Concerns\FromArray;

class PalletStoredItemTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['pallet_type', 'pallet_customer_reference', 'pallet_notes', 'sku_reference', 'sku_quantity', 'sku_name'],
        ];
    }
}
