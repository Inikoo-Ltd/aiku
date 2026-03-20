<?php

/*
 * Author: Koding Aiku
 * Created: Tue, 17 Mar 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Accounting;

use App\Models\Accounting\IntrastatImportTimeSeriesRecord;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class IntrastatImportExcel implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
{
    public function __construct(
        protected Organisation $organisation,
        protected array $filters = []
    ) {
    }

    public function query(): Builder
    {
        $query = IntrastatImportTimeSeriesRecord::query()
            ->where('intrastat_import_time_series_records.organisation_id', $this->organisation->id)
            ->where('intrastat_import_time_series_records.frequency', 'D')
            ->join('intrastat_import_time_series', 'intrastat_import_time_series_records.intrastat_import_time_series_id', '=', 'intrastat_import_time_series.id')
            ->leftJoin('countries', 'intrastat_import_time_series.country_id', '=', 'countries.id')
            ->leftJoin('tax_categories', 'intrastat_import_time_series.tax_category_id', '=', 'tax_categories.id')
            ->select([
                'intrastat_import_time_series_records.from as date',
                'intrastat_import_time_series.tariff_code',
                'countries.code as country_code',
                'countries.name as country_name',
                'tax_categories.name as tax_category_name',
                'intrastat_import_time_series_records.supplier_deliveries_count',
                'intrastat_import_time_series_records.invoices_count',
                'intrastat_import_time_series_records.valid_tax_numbers_count',
                'intrastat_import_time_series_records.invalid_tax_numbers_count',
                'intrastat_import_time_series_records.quantity',
                'intrastat_import_time_series_records.value_org_currency',
                DB::raw("'" . $this->organisation->currency->code . "' as currency_code"),
                'intrastat_import_time_series_records.weight',
            ])
            ->orderBy('intrastat_import_time_series_records.from');

        if (!empty($this->filters['between']['date'])) {
            $raw = $this->filters['between']['date'];
            [$start, $end] = explode('-', $raw);

            $startDate = Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $endDate   = Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $query->whereBetween('intrastat_import_time_series_records.from', [$startDate, $endDate]);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            __('Date'),
            __('Tariff Code'),
            __('Country Code'),
            __('Country Name'),
            __('VAT Category'),
            __('Deliveries'),
            __('Invoices'),
            __('Valid Tax Numbers'),
            __('Invalid Tax Numbers'),
            __('Quantity'),
            __('Value'),
            __('Currency'),
            __('Weight (kg)'),
        ];
    }

    public function map($row): array
    {
        return [
            $row->date ? Carbon::parse($row->date)->format('Y-m-d') : '',
            $row->tariff_code,
            $row->country_code,
            $row->country_name,
            $row->tax_category_name ?? '',
            $row->supplier_deliveries_count,
            $row->invoices_count,
            $row->valid_tax_numbers_count,
            $row->invalid_tax_numbers_count,
            number_format($row->quantity, 2, '.', ''),
            number_format($row->value_org_currency, 2, '.', ''),
            $row->currency_code,
            number_format($row->weight / 1000, 2, '.', ''),
        ];
    }
}
