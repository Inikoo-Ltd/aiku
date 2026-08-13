<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\OrgStock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class OrgStockHistoryExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected OrgStock $orgStock,
        protected array $filters = []
    ) {
    }

    public function query(): Builder
    {
        $query = DB::table('org_stock_histories')
            ->where('org_stock_id', $this->orgStock->id)
            ->select([
                'date',
                'quantity_in_locations',
                'number_locations',
                'org_stock_fifo_value',
                'org_stock_wac_value',
                'org_stock_lpp_value',
                'grp_stock_lpp_value',
                'fifo_per_sku',
                'wac_per_sku',
                'lpp_per_sku',
            ])
            ->orderBy('date', 'desc');

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

        $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function headings(): array
    {
        return [
            __('Date'),
            __('Quantity'),
            __('Locations'),
            __('Stock Value FIFO (recommended)'),
            __('Stock Value WAC (second choice)'),
            __('Stock Value LPP (not recommended)'),
            __('Stock Value LPP (Grp currency)'),
            __('Unit Value FIFO (recommended)'),
            __('Unit Value WAC (second choice)'),
            __('Unit Value LPP (not recommended)'),
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
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function map($row): array
    {
        return [
            (string) Carbon::parse($row->date)->format('Y-m-d'),
            number_format((float) ($row->quantity_in_locations ?? 0), 2, '.', ''),
            (string) ($row->number_locations ?? '0'),
            $row->org_stock_fifo_value !== null ? number_format((float) $row->org_stock_fifo_value, 2, '.', '') : '',
            $row->org_stock_wac_value !== null ? number_format((float) $row->org_stock_wac_value, 2, '.', '') : '',
            number_format((float) ($row->org_stock_lpp_value ?? 0), 2, '.', ''),
            number_format((float) ($row->grp_stock_lpp_value ?? 0), 2, '.', ''),
            $row->fifo_per_sku !== null ? number_format((float) $row->fifo_per_sku, 2, '.', '') : '',
            $row->wac_per_sku !== null ? number_format((float) $row->wac_per_sku, 2, '.', '') : '',
            $row->lpp_per_sku !== null ? number_format((float) $row->lpp_per_sku, 2, '.', '') : '',
        ];
    }
}
