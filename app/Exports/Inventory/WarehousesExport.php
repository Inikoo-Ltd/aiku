<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 15:21:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WarehousesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Warehouse|Builder
    {
        return Warehouse::query();
    }

    /** @var Warehouse */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->name,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Name',
            'Created At',
        ];
    }
}
