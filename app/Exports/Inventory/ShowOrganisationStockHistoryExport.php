<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 17:25:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ShowOrganisationStockHistoryExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected OrganisationStockHistory $organisationStockHistory,
        protected string $tab = 'org_stocks'
    ) {
    }

    public function query(): Builder
    {
        if ($this->tab === 'location_org_stocks') {
            // TODO: after running repair:location_org_stock_histories_organisation_stock_history_id, remove the join to org_stock_histories and use this instead:
            // ->where('location_org_stock_histories.organisation_stock_history_id', $this->organisationStockHistory->id)

            return DB::table('location_org_stock_histories')
                ->join('org_stock_histories', 'location_org_stock_histories.org_stock_history_id', '=', 'org_stock_histories.id')
                ->join('org_stocks', 'location_org_stock_histories.org_stock_id', '=', 'org_stocks.id')
                ->join('locations', 'location_org_stock_histories.location_id', '=', 'locations.id')
                ->select([
                    'org_stocks.code as stock_code',
                    'org_stocks.name as stock_name',
                    'locations.code as location_code',
                    'location_org_stock_histories.quantity_in_locations',
                    'location_org_stock_histories.org_stock_value',
                ])
                ->where('org_stock_histories.organisation_stock_history_id', $this->organisationStockHistory->id)
                ->orderBy('org_stocks.code')
                ->orderBy('locations.code');
        }

        return DB::table('org_stock_histories')
            ->join('org_stocks', 'org_stock_histories.org_stock_id', '=', 'org_stocks.id')
            ->select([
                'org_stocks.code',
                'org_stocks.name',
                'org_stock_histories.quantity_in_locations',
                'org_stock_histories.org_stock_value',
                'org_stock_histories.sold_within_1y',
                'org_stock_histories.last_sold_date',
                'org_stock_histories.non_moving_1y',
            ])
            ->where('org_stock_histories.organisation_stock_history_id', $this->organisationStockHistory->id)
            ->orderBy('org_stocks.code');
    }

    public function headings(): array
    {
        if ($this->tab === 'location_org_stocks') {
            return [
                __('SKU Code'),
                __('SKU Name'),
                __('Location'),
                __('Quantity'),
                __('Stock Value'),
            ];
        }

        return [
            __('SKU Code'),
            __('SKU Name'),
            __('Quantity'),
            __('Stock Value'),
            __('Sold Within 1 Year'),
            __('Last Sold Date'),
            __('Non Moving 1 Year'),
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
        ];
    }

    public function map($row): array
    {
        $orgSymbol = $this->organisationStockHistory->organisation->currency->symbol;
        if ($this->tab === 'location_org_stocks') {
            return [
                (string)($row->stock_code ?? ''),
                (string)($row->stock_name ?? ''),
                (string)($row->location_code ?? ''),
                number_format((float)($row->quantity_in_locations ?? 0), 2, '.', ''),
                $orgSymbol.number_format((float)($row->org_stock_value ?? 0), 2, '.', ''),
            ];
        }

        return [
            (string)($row->code ?? ''),
            (string)($row->name ?? ''),
            number_format((float)($row->quantity_in_locations ?? 0), 2, '.', ''),
            $orgSymbol.number_format((float)($row->org_stock_value ?? 0), 2, '.', ''),
            $row->sold_within_1y ? __('Yes') : __('No'),
            $row->last_sold_date ?? '',
            (string)($row->non_moving_1y ?? '0'),
        ];
    }
}
