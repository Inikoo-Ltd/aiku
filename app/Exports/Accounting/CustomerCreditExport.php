<?php

namespace App\Exports\Accounting;

use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CustomerCreditExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected Organisation $organisation,
        protected string $beforeDate,
    ) {
    }

    public function query(): Builder
    {
        return Customer::query()
            ->where('customers.organisation_id', $this->organisation->id)
            ->join('shops', 'customers.shop_id', '=', 'shops.id')
            ->leftJoin('credit_transactions', function ($join) {
                $join->on('credit_transactions.customer_id', '=', 'customers.id')
                    ->where('credit_transactions.date', '<', $this->beforeDate);
            })
            ->join('organisations', 'organisations.id', '=', 'customers.organisation_id')
            ->join('currencies', 'currencies.id', '=', 'organisations.currency_id')
            ->groupBy('customers.id', 'customers.slug', 'customers.reference', 'customers.name', 'customers.email', 'shops.code', 'currencies.symbol', 'currencies.code')
            ->havingRaw('COALESCE(SUM(credit_transactions.org_amount), 0) != 0')
            ->selectRaw('
                customers.id,
                customers.slug,
                customers.reference,
                customers.name,
                customers.email,
                shops.code as shop_code,
                COALESCE(SUM(credit_transactions.org_amount), 0) as credit_balance,
                MAX(credit_transactions.date) as latest_transaction_date,
                currencies.symbol as currency_symbol,
                currencies.code as currency_code
            ')
            ->orderByDesc('latest_transaction_date');
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Name',
            'Email',
            'Shop Code',
            'Latest Transaction',
            'Balance',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function map($row): array
    {
        return [
            (string) $row->reference,
            (string) $row->name,
            (string) $row->email,
            (string) $row->shop_code,
            $row->latest_transaction_date ? date('d/m/Y', strtotime($row->latest_transaction_date)) : '',
            ($row->currency_symbol ?? '') . number_format((float) $row->credit_balance, 2, '.', ''),
        ];
    }
}
