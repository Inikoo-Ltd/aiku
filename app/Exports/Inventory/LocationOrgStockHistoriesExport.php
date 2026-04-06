<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
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

class LocationOrgStockHistoriesExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected Organisation $organisation,
        protected array $filters = []
    ) {
    }

    public function query(): Builder
    {
        $query = DB::table('location_org_stock_histories as losh')
            ->join('org_stocks as os', 'os.id', '=', 'losh.org_stock_id')
            ->join('locations as l', 'l.id', '=', 'losh.location_id')
            ->select([
                'losh.date',
                'os.code as stock_code',
                'os.name as stock_name',
                'l.code as location_code',
                'losh.quantity_in_locations',
                'losh.actual_quantity_in_locations',
            ])
            ->where('os.organisation_id', $this->organisation->id)
            ->orderBy('losh.date', 'desc')
            ->orderBy('os.code')
            ->orderBy('l.code');

        $this->applyDateFilter($query);

        return $query;
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

        $query->whereBetween('losh.date', [$startDate, $endDate]);
    }

    public function headings(): array
    {
        return [
            __('Date'),
            __('SKU Code'),
            __('SKU Name'),
            __('Location'),
            __('Quantity'),
            __('Actual Quantity'),
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
            (string) Carbon::parse($row->date)->format('Y-m-d'),
            (string) ($row->stock_code ?? ''),
            (string) ($row->stock_name ?? ''),
            (string) ($row->location_code ?? ''),
            number_format((float) ($row->quantity_in_locations ?? 0), 2, '.', ''),
            number_format((float) ($row->actual_quantity_in_locations ?? 0), 2, '.', ''),
        ];
    }
}
