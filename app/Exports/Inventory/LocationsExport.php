<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 15:21:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder; // Perbaiki import Builder
use Illuminate\Database\Query\Builder as QueryBuilder;
// use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LocationsExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    protected Warehouse $warehouse;
    protected array $columns;

    public function __construct(Warehouse $warehouse, array $columns)
    {
        $this->warehouse = $warehouse;
        $this->columns = $columns;
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        return Location::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->with(['warehouse', 'warehouseArea']);
    }

    /** @var Location $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->warehouse->name,
            isset($row->warehouseArea) ? $row->warehouseArea->name : null,
            $row->stock_value,
            $row->is_empty,
            $row->status->value,
            $row->created_at
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Warehouse Name',
            'Warehouse Area Name',
            'Stock Value',
            'Is Empty',
            'Status',
            'Created At'
        ];
    }
}
