<?php

namespace App\Exports\Accounting;

use App\Models\Accounting\Payment;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Payment|Builder
    {
        return Payment::query();
    }

    /** @var Payment */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->paymentAccount->name,
            $row->type,
            $row->reference,
            $row->shop->name,
            $row->customer->name,
            $row->currency->code,
            $row->amount,
            $row->org_amount,
            $row->grp_amount,
            $row->with_refund,
            $row->status,
            $row->state,
            $row->date,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Payment Account Name',
            'Type',
            'Reference',
            'Shop Name',
            'Customer Name',
            'Currency',
            'Amount',
            'TC Amount',
            'GC Amount',
            'With Refund',
            'Status',
            'State',
            'Date',
        ];
    }
}
