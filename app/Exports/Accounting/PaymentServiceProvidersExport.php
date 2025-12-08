<?php

namespace App\Exports\Accounting;

use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentServiceProvidersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|PaymentServiceProvider|Builder
    {
        return PaymentServiceProvider::query();
    }

    /** @var PaymentServiceProvider */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->type,
            $row->last_used_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Type',
            'Last Used At',
        ];
    }
}
