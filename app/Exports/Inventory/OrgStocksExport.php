<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 15:21:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrgStocksExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Organisation|OrgStockFamily $parent)
    {
    }

    public function query(): Builder
    {
        return OrgStock::query()
            ->with('orgStockFamily')
            ->when(
                $this->parent instanceof OrgStockFamily,
                fn (Builder $query) => $query->where('org_stock_family_id', $this->parent->id),
                fn (Builder $query) => $query->where('organisation_id', $this->parent->id)
            )
            ->orderBy('code');
    }

    /** @var OrgStock $row */
    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            $row->orgStockFamily?->code,
            $row->state->value,
            $row->quantity_in_locations,
            $row->quantity_available,
            $row->sku_value,
            $row->value_in_locations,
            $row->activated_in_organisation_at,
            $row->discontinuing_in_organisation_at,
            $row->discontinued_in_organisation_at,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Family',
            'State',
            'Quantity in Locations',
            'Quantity Available',
            'SKO Value',
            'Value in Locations',
            'Activated At',
            'Discontinuing At',
            'Discontinued At',
            'Created At',
        ];
    }
}
