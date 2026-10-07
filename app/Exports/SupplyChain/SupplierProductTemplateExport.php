<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\SupplyChain;

use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum;
use Maatwebsite\Excel\Concerns\FromArray;

class SupplierProductTemplateExport implements FromArray
{
    public const array ORDER_COLUMNS = ['UK', 'SK', 'ES', 'Aroma'];

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            ...array_map(fn (SupplierProductSheetColumnEnum $column) => $column->heading(), SupplierProductSheetColumnEnum::cases()),
            ...array_map(fn (string $organisation) => 'Order Cartons '.$organisation, self::ORDER_COLUMNS),
        ];
    }

    public function array(): array
    {
        return [
            [
                ...array_map(fn (SupplierProductSheetColumnEnum $column) => $column->isRequired() ? 'Required' : 'Opt', SupplierProductSheetColumnEnum::cases()),
                ...array_fill(0, count(self::ORDER_COLUMNS), 'Opt'),
            ],
            self::headings(),
        ];
    }
}
