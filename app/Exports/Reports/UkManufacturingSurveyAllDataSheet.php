<?php

namespace App\Exports\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class UkManufacturingSurveyAllDataSheet extends DefaultValueBinder implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(
        protected int $organisationId,
        protected string $startDate,
        protected string $endDate,
    ) {
    }

    public function collection(): Collection
    {
        return DB::table('invoice_transactions as it')
            ->join('invoice_transaction_has_trade_units as ittu', 'it.id', '=', 'ittu.invoice_transaction_id')
            ->join('trade_units as tu', 'ittu.trade_unit_id', '=', 'tu.id')
            ->join('products as p', function ($join) {
                $join->on('it.model_id', '=', 'p.id')
                    ->where('it.model_type', '=', 'Product');
            })
            ->where('it.organisation_id', $this->organisationId)
            ->whereNotNull('tu.tariff_code')
            ->where('tu.tariff_code', '!=', '')
            ->whereBetween('it.date', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->select([
                DB::raw("LEFT(REPLACE(tu.tariff_code, ' ', ''), 6) as tariff_6digit"),
                'tu.tariff_code as full_tariff_code',
                DB::raw("STRING_AGG(DISTINCT p.code, ',') as part_references"),
                'tu.name as product_unit_name',
                DB::raw("ROUND(SUM(it.quantity)::numeric, 4)::text as units_invoiced"),
                DB::raw("ROUND(SUM(it.org_net_amount)::numeric, 2)::text as amount"),
                DB::raw("ROUND(SUM(it.quantity * COALESCE(tu.gross_weight / 1000.0, 0))::numeric, 3)::text as weight_kg"),
            ])
            ->groupBy(DB::raw("LEFT(REPLACE(tu.tariff_code, ' ', ''), 6)"), 'tu.tariff_code', 'tu.name')
            ->orderByDesc(DB::raw('SUM(it.org_net_amount)'))
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tariff Code (6 digit)',
            'Full Tariff Code',
            'Part References',
            'Product Unit Name',
            'Units Invoiced',
            'Amount (£)',
            'Weight (kg)',
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        return (new StringValueBinder())->bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'All Data';
    }
}
