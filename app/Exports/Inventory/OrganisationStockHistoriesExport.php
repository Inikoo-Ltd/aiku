<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Exports\Inventory;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
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
        $bucket = $this->filters['tab'] ?? 'daily';

        $query = DB::table('organisation_stock_histories')
            ->select([
                'date as bucket',
                'org_stock_value',
                'grp_stock_value',
                'number_org_stocks',
                'number_out_of_stock_org_stocks',
                'number_location_org_stocks',
            ])
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('date', 'desc');

        match ($bucket) {
            'weekly'  => $query->where('is_week', true),
            'monthly' => $query->where('is_month', true),
            'yearly'  => $query->where('is_year', true),
            default   => $query->where('is_week', false)->where('is_month', false)->where('is_year', false),
        };

        return $query;
    }

    protected function isSameCurrency(): bool
    {
        return $this->organisation->currency->code === $this->organisation->group->currency->code;
    }

    public function headings(): array
    {
        $orgCurrency = $this->organisation->currency->code;

        $headings = [
            __('Date'),
            __('Total SKUs'),
            __('Out of Stock'),
            __('In Locations'),
            __('Stock Value').' ('.$orgCurrency.')',
        ];

        if (!$this->isSameCurrency()) {
            $headings[] = __('Stock Value').' ('.$this->organisation->group->currency->code.')';
        }

        return $headings;
    }

    public function columnFormats(): array
    {
        $formats = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];

        if (!$this->isSameCurrency()) {
            $formats['F'] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
    }

    public function map($row): array
    {
        $orgSymbol = $this->organisation->currency->symbol;

        $data = [
            (string) $row->bucket,
            (string) ($row->number_org_stocks ?? '0'),
            (string) ($row->number_out_of_stock_org_stocks ?? '0'),
            (string) ($row->number_location_org_stocks ?? '0'),
            $orgSymbol.number_format((float) ($row->org_stock_value ?? 0), 2, '.', ''),
        ];

        if (!$this->isSameCurrency()) {
            $data[] = $this->organisation->group->currency->symbol.number_format((float) ($row->grp_stock_value ?? 0), 2, '.', '');
        }

        return $data;
    }
}
