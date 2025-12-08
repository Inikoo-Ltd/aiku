<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 11:40:49 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Exports\Procurement;

use App\Models\SupplyChain\Supplier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrgSuppliersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|\App\Models\SupplyChain\Supplier|Builder
    {
        return Supplier::query();
    }

    /** @var Supplier */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            ! isset($row->agent),
            isset($row->agent) ? $row->agent->name : null,
            $row->name,
            $row->email,
            $row->phone,
            $row->contact_name,
            $row->currency->code,
            $row->type,
            $row->location,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Independent Supplier',
            'Agent Name',
            'Name',
            'Email',
            'Phone',
            'Contact Name',
            'Currency',
            'Type',
            'Location',
            'Created At',
        ];
    }
}
