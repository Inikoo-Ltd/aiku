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

class UkManufacturingSurveyManufacturedSheet extends DefaultValueBinder implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    protected array $manufacturedTariffCodes = [
        '330730',
        '340111',
        '330741',
        '630790',
        '330510',
        '330499',
        '340600',
        '630520',
    ];

    public function __construct(
        protected int $organisationId,
        protected string $startDate,
        protected string $endDate,
    ) {
    }

    public function collection(): Collection
    {
        $rows = DB::table('invoice_transactions as it')
            ->join('invoice_transaction_has_trade_units as ittu', 'it.id', '=', 'ittu.invoice_transaction_id')
            ->join('trade_units as tu', 'ittu.trade_unit_id', '=', 'tu.id')
            ->join('products as p', function ($join) {
                $join->on('it.model_id', '=', 'p.id')
                    ->where('it.model_type', '=', 'Product');
            })
            ->where('it.organisation_id', $this->organisationId)
            ->whereNotNull('tu.tariff_code')
            ->where('tu.tariff_code', '!=', '')
            ->whereIn(DB::raw("LEFT(REPLACE(tu.tariff_code, ' ', ''), 6)"), $this->manufacturedTariffCodes)
            ->whereBetween('it.date', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->select([
                DB::raw("LEFT(REPLACE(tu.tariff_code, ' ', ''), 6) as tariff_6digit"),
                DB::raw("STRING_AGG(DISTINCT p.code, ',') as part_references"),
                DB::raw("STRING_AGG(DISTINCT tu.name, '; ') as product_unit_names"),
                DB::raw("ROUND(SUM(it.org_net_amount)::numeric, 2)::text as amount"),
            ])
            ->groupBy(DB::raw("LEFT(REPLACE(tu.tariff_code, ' ', ''), 6)"))
            ->orderByDesc(DB::raw('SUM(it.org_net_amount)'))
            ->get();

        $collection = collect($rows->map(fn ($row) => [
            $row->tariff_6digit,
            $row->part_references,
            $row->product_unit_names,
            $row->amount,
        ])->all());

        $total = $rows->sum(fn ($row) => (float) $row->amount);
        $collection->push(['', 'Total Manufactured', '', number_format($total, 2, '.', '')]);

        return $collection;
    }

    public function headings(): array
    {
        return [
            'Tariff Code (6 digit)',
            'Part References',
            'Product Descriptions',
            'Amount (£)',
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        return (new StringValueBinder())->bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'Manufactured';
    }
}
