<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 15:21:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Goods\Stock;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrgStocksExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Stock|Builder
    {
        return Stock::query();
    }

    /** @var Stock */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            isset($row->stockFamily) ? $row->stockFamily->name : null,
            $row->trade_unit_composition->value,
            $row->state->value,
            $row->sellable,
            $row->raw_material,
            $row->barcode,
            $row->units_per_pack,
            $row->units_per_carton,
            $row->quantity_in_locations,
            $row->quantity_status,
            $row->available_forecast,
            $row->number_locations,
            $row->unit_value,
            $row->value_in_locations,
            $row->activated_at,
            $row->discontinuing_at,
            $row->discontinued_at,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Stock Name',
            'Trade Unit Composition',
            'State',
            'Raw Material',
            'Barcode',
            'Units Per Pack',
            'Units Per Carton',
            'Quantity in Locations',
            'Quantity Status',
            'Available Forecast',
            'Number Locations',
            'Activated At',
            'Discontinuing At',
            'Discontinued At',
            'Created At',
        ];
    }
}
