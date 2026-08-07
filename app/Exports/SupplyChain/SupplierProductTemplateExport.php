<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\SupplyChain;

use Maatwebsite\Excel\Concerns\FromArray;

class SupplierProductTemplateExport implements FromArray
{
    public const HEADINGS = [
        'Id: Supplier Part Key',
        "Supplier's product code",
        "Supplier's unit description",
        'Part reference',
        'Units per SKO',
        'SKOs per carton',
        'Carton CBM',
        'Unit cost',
        'Unit extra costs %',
        'Minimum order (cartons)',
        'Average delivery time (days)',
        'Availability',
    ];

    public function array(): array
    {
        return [
            self::HEADINGS,
            ['new', 'SUP-001', 'Example product', '', 12, 4, 0.08, 1.25, 0, 1, 21, 'Available'],
        ];
    }
}
