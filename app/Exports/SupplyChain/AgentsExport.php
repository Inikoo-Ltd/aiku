<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 11:28:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Exports\SupplyChain;

use App\Models\SupplyChain\Agent;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AgentsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Agent|Builder
    {
        return Agent::query();
    }

    /** @var \App\Models\SupplyChain\Agent */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->name,
            $row->email,
            $row->phone,
            $row->owner->name,
            $row->contact_name,
            $row->currency->code,
            $row->location,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Name',
            'Email',
            'Phone',
            'Owner Name',
            'Contact Name',
            'Currency',
            'Location',
            'Created At',
        ];
    }
}
