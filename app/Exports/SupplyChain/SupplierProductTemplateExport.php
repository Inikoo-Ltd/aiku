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
        "Supplier's product code *",
        "Supplier's unit description *",
        'Family',
        'Part reference *',
        'Unit label *',
        'Units per SKO *',
        'SKO description (picking aid) *',
        'SKO Barcode',
        'SKOs per carton *',
        'Recommended SKOs per selling outer',
        'Minimum order (cartons) *',
        'Average delivery time (days)',
        'Carton CBM',
        'Unit cost *',
        'Unit expense *',
        'Unit extra costs %',
        'Unit recommended price',
        'Unit recommended RRP',
        'Unit recommended description (website) *',
        'Unit barcode (EAN-13, for website)',
        'Unit weight (kg)',
        'Unit dimensions (l x w x h) in cm',
        'SKO weight (kg)',
        'SKO dimensions (l x w x h) in cm',
        'Materials',
        'Country of origin *',
        'Tariff code',
        'Duty rate',
        'HTSUS',
        'UN number',
        'UN class',
        'Packing group',
        'Proper shipping name',
        'Hazard identification number',
        'CPNP Number',
        'UFI',
        'Carton Weight',
        'Carton Barcode',
    ];

    public function array(): array
    {
        return [
            self::HEADINGS,
            [
                'new',
                'SUP-001',
                'Example product',
                '',
                'SUP-001',
                'Example unit',
                12,
                'Example SKO',
                '',
                4,
                '',
                1,
                21,
                0.08,
                1.25,
                '',
                0,
                '',
                '',
                'Example product description',
                '',
                '',
                '',
                '',
                '',
                '',
                'GBR',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];
    }
}
