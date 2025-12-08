<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 09:17:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Procurement;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SupplierProductsExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|\App\Models\SupplyChain\SupplierProduct|Builder
    {
        return \App\Models\SupplyChain\SupplierProduct::query();
    }

    /** @var \App\Models\SupplyChain\SupplierProduct */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->name,
            isset($row->supplier) ? $row->supplier['name'] : null,
            isset($row->agent) ? $row->agent['name'] : null,
            $row->state->value,
            $row->status,
            $row->stock_quantity_status,
            $row->cost,
            $row->units_per_pack,
            $row->units_per_carton,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Name',
            'Supplier Name',
            'Agent Name',
            'State',
            'Status',
            'Stock Quantity Status',
            'Cost',
            'Units Per Pack',
            'Units Per Carton',
            'Created At',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
