<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 15:21:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\WarehouseArea;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WarehouseAreasExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|WarehouseArea|Builder
    {
        return WarehouseArea::query();
    }

    /** @var WarehouseArea */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->warehouse->name,
            $row->name,
            $row->unit_quantity,
            $row->value,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Warehouse Name',
            'Name',
            'Unit Quantity',
            'Value',
            'Created At',
        ];
    }
}
