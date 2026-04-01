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
        $query = DB::table('organisation_stock_histories')
            ->select([
                'date as bucket',
                'org_stock_value',
                'grp_stock_value',
                'org_stock_commercial_value',
                'grp_stock_commercial_value',
                'number_org_stocks',
                'number_out_of_stock_org_stocks',
                'number_location_org_stocks',
            ])
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('date', 'desc');

        $buckets = $this->filters['buckets'] ? explode(',', $this->filters['buckets']) : null;

        if ($buckets) {
            $query->where(function ($q) use ($buckets) {
                foreach ($buckets as $bucket) {
                    $q->orWhere(function ($inner) use ($bucket) {
                        match (trim($bucket)) {
                            'weekly'  => $inner->where('is_week', true),
                            'monthly' => $inner->where('is_month', true),
                            'yearly'  => $inner->where('is_year', true),
                            default   => $inner->where('is_week', false)->where('is_month', false)->where('is_year', false),
                        };
                    });
                }
            });
        }

        return $query;
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
        return [
            (string) $row->bucket,
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
