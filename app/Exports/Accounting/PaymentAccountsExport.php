<?php

namespace App\Exports\Accounting;

use App\Models\Accounting\PaymentAccount;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentAccountsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|PaymentAccount|Builder
    {
        return PaymentAccount::query();
    }

    /** @var PaymentAccount */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->name,
            $row->paymentServiceProvider->type,
            $row->last_used_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Name',
            'Type',
            'Last Used At',
        ];
    }
}
