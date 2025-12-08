<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jul 2024 11:13:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Exports\Web;

use App\Models\Web\Banner;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BannersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Banner|Builder
    {
        return Banner::query();
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->name,
            $row->state,
            $row->live_at,
            $row->data,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Name',
            'State',
            'Live At',
            'Data',
        ];
    }
}
