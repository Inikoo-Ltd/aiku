<?php

namespace App\Exports\Accounting;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SageInvoicesExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public function __construct(
        protected Organisation $parent,
        protected string $startDate,
        protected string $endDate,
    ) {
    }

    public function query(): Builder
    {
        return Invoice::query()
            ->with([
                'customer',
                'taxCategory',
            ])
            ->where('in_process', false)
            ->where('organisation_id', $this->parent->id)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->whereHas('customer', function (Builder $query) {
                $query->where('is_credit_customer', true);
            });
    }

    /** @var Invoice $row */
    public function map($row): array
    {
        return [
            $row->type === InvoiceTypeEnum::REFUND ? 'SC' : 'SI',
            $row->customer->accounting_reference,
            '4000', // TODO: Make configurable when sales_account_code field added
            '0',
            $row->date->format('Y-m-d'),
            $row->reference,
            $row->customer->name,
            $row->net_amount,
            $row->taxCategory->label, // TODO: Map from taxCategory when tax_code field added
            $row->tax_amount,
            '1.000000',
            $row->reference,
            'Aiku Sales',
            null,
            null,
        ];
    }

    public function headings(): array
    {
        return [
            'Type',
            'Account Reference',
            'Nominal A/C Ref',
            'Department Code',
            'Date',
            'Reference',
            'Details',
            'Net Amount',
            'Tax Code',
            'Tax Amount',
            'Exchange Rate',
            'Extra Reference',
            'User Name',
            'Project Refn',
            'Cost Code Refn',
        ];
    }
}
