<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Exports\Inventory;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class OrganisationStockHistoriesExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected Organisation $organisation,
        protected array $filters = []
    ) {
    }

    public function query(): Builder
    {
        $period = $this->filters['tab'] ?? 'daily';

        if ($period === 'daily') {
            $query = DB::table('organisation_stock_histories')
                ->selectRaw('date as period, org_stock_value, grp_stock_value, org_stock_commercial_value, grp_stock_commercial_value, number_org_stocks, number_out_of_stock_org_stocks, number_location_org_stocks')
                ->where('organisation_id', $this->organisation->id)
                ->orderBy('date', 'desc');

            $this->applyDateFilter($query);

            return $query;
        }

        $truncUnit = match ($period) {
            'weekly'  => 'week',
            'monthly' => 'month',
            'yearly'  => 'year',
            default   => 'week',
        };

        $query = DB::table('organisation_stock_histories')
            ->selectRaw(
                "DATE_TRUNC('{$truncUnit}', date) as period,
                ROUND(AVG(org_stock_value::numeric), 2) as org_stock_value,
                ROUND(AVG(grp_stock_value::numeric), 2) as grp_stock_value,
                ROUND(AVG(org_stock_commercial_value::numeric), 2) as org_stock_commercial_value,
                ROUND(AVG(grp_stock_commercial_value::numeric), 2) as grp_stock_commercial_value,
                ROUND(AVG(number_org_stocks)) as number_org_stocks,
                ROUND(AVG(number_out_of_stock_org_stocks)) as number_out_of_stock_org_stocks,
                ROUND(AVG(number_location_org_stocks)) as number_location_org_stocks"
            )
            ->where('organisation_id', $this->organisation->id);

        $this->applyDateFilter($query);

        return $query
            ->groupByRaw("DATE_TRUNC('{$truncUnit}', date)")
            ->orderByRaw("DATE_TRUNC('{$truncUnit}', date) DESC");
    }

    private function applyDateFilter(Builder $query): void
    {
        $between = $this->filters['between'] ?? [];

        if (!isset($between['date'])) {
            return;
        }

        $parts = explode('-', $between['date']);

        if (count($parts) !== 2) {
            return;
        }

        [$start, $end] = array_map('trim', $parts);

        $startDate = Carbon::createFromFormat('Ymd', $start)->startOfDay()->toDateTimeString();
        $endDate   = Carbon::createFromFormat('Ymd', $end)->endOfDay()->toDateTimeString();

        $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function headings(): array
    {
        return [
            __('Date'),
            __('Total SKUs'),
            __('Out of Stock'),
            __('In Locations'),
            __('Stock Value (Org)'),
            __('Stock Value (Grp)'),
            __('Commercial Value (Org)'),
            __('Commercial Value (Grp)'),
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
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function map($row): array
    {
        $period = $row->period;

        if ($period instanceof \DateTimeInterface) {
            $period = Carbon::instance($period)->format('Y-m-d');
        } elseif (is_string($period)) {
            $period = Carbon::parse($period)->format('Y-m-d');
        }

        return [
            (string) $period,
            (string) ($row->number_org_stocks ?? '0'),
            (string) ($row->number_out_of_stock_org_stocks ?? '0'),
            (string) ($row->number_location_org_stocks ?? '0'),
            number_format((float) ($row->org_stock_value ?? 0), 2, '.', ''),
            number_format((float) ($row->grp_stock_value ?? 0), 2, '.', ''),
            number_format((float) ($row->org_stock_commercial_value ?? 0), 2, '.', ''),
            number_format((float) ($row->grp_stock_commercial_value ?? 0), 2, '.', ''),
        ];
    }
}
