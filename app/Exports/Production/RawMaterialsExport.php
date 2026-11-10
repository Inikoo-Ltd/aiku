<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Production;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class RawMaterialsExport implements FromArray, WithHeadings, WithStrictNullComparison
{
    /**
     * @param  array<int, array<int, mixed>>  $rows  from ExportRawMaterials::rows
     */
    public function __construct(protected array $rows, protected string $currency)
    {
    }

    public function headings(): array
    {
        return [
            'Code',
            'Description',
            'Unit',
            'SKO',
            'SKO name',
            trim("Unit cost $this->currency"),
            'Stock in warehouse',
            'Artefacts using it',
            'Status',
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
