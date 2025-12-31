<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait WithTimeSeriesData
{
    protected function getYearlySalesData(Model $model, string $timeSeriesRecordsTable): array
    {
        $yearlyTimeSeries = $model->timeSeries()
            ->where('frequency', TimeSeriesFrequencyEnum::YEARLY)
            ->first();

        if (!$yearlyTimeSeries) {
            return [];
        }

        return $yearlyTimeSeries->records()
            ->orderBy('from', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($record) use ($timeSeriesRecordsTable) {
                $from = Carbon::parse($record->from);
                $year = $from->year;

                // Get previous year data
                $previousYearRecord = DB::table($timeSeriesRecordsTable)
                    ->where($this->getTimeSeriesForeignKey($timeSeriesRecordsTable), $record->{$this->getTimeSeriesForeignKey($timeSeriesRecordsTable)})
                    ->whereYear('from', $year - 1)
                    ->first();

                $previousYearSales = $previousYearRecord->sales_grp_currency ?? 0;
                $previousYearInvoices = $previousYearRecord->invoices ?? 0;

                $salesDelta = $record->sales_grp_currency - $previousYearSales;
                $salesDeltaPercentage = $previousYearSales > 0
                    ? (($salesDelta / $previousYearSales) * 100)
                    : 0;

                $invoicesDelta = $record->invoices - $previousYearInvoices;
                $invoicesDeltaPercentage = $previousYearInvoices > 0
                    ? (($invoicesDelta / $previousYearInvoices) * 100)
                    : 0;

                return [
                    'year' => $year,
                    'total_sales' => (float) $record->sales_grp_currency,
                    'total_invoices' => (int) $record->invoices,
                    'sales_delta' => (float) $salesDelta,
                    'sales_delta_percentage' => (float) $salesDeltaPercentage,
                    'previous_year_sales' => (float) $previousYearSales,
                    'invoices_delta' => (int) $invoicesDelta,
                    'invoices_delta_percentage' => (float) $invoicesDeltaPercentage,
                    'previous_year_invoices' => (int) $previousYearInvoices,
                ];
            })
            ->reverse()
            ->values()
            ->toArray();
    }

    protected function getQuarterlySalesData(Model $model, string $timeSeriesRecordsTable): array
    {
        $quarterlyTimeSeries = $model->timeSeries()
            ->where('frequency', TimeSeriesFrequencyEnum::QUARTERLY)
            ->first();

        if (!$quarterlyTimeSeries) {
            return [];
        }

        return $quarterlyTimeSeries->records()
            ->orderBy('from', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($record) use ($timeSeriesRecordsTable) {
                $from = Carbon::parse($record->from);
                $year = $from->year;
                $quarter = $from->quarter;

                // Get previous year same quarter data
                $previousYearRecord = DB::table($timeSeriesRecordsTable)
                    ->where($this->getTimeSeriesForeignKey($timeSeriesRecordsTable), $record->{$this->getTimeSeriesForeignKey($timeSeriesRecordsTable)})
                    ->whereYear('from', $year - 1)
                    ->whereRaw('EXTRACT(QUARTER FROM "from") = ?', [$quarter])
                    ->first();

                $previousYearSales = $previousYearRecord->sales_grp_currency ?? 0;
                $previousYearInvoices = $previousYearRecord->invoices ?? 0;

                $salesDelta = $record->sales_grp_currency - $previousYearSales;
                $salesDeltaPercentage = $previousYearSales > 0
                    ? (($salesDelta / $previousYearSales) * 100)
                    : 0;

                $invoicesDelta = $record->invoices - $previousYearInvoices;
                $invoicesDeltaPercentage = $previousYearInvoices > 0
                    ? (($invoicesDelta / $previousYearInvoices) * 100)
                    : 0;

                return [
                    'quarter' => "Q{$quarter} {$year}",
                    'quarter_number' => $quarter,
                    'year' => $year,
                    'total_sales' => (float) $record->sales_grp_currency,
                    'total_invoices' => (int) $record->invoices,
                    'sales_delta' => (float) $salesDelta,
                    'sales_delta_percentage' => (float) $salesDeltaPercentage,
                    'previous_year_sales' => (float) $previousYearSales,
                    'invoices_delta' => (int) $invoicesDelta,
                    'invoices_delta_percentage' => (float) $invoicesDeltaPercentage,
                    'previous_year_invoices' => (int) $previousYearInvoices,
                ];
            })
            ->reverse()
            ->values()
            ->toArray();
    }

    protected function getTotalSalesData(Model $model): array
    {
        $yearlyTimeSeries = $model->timeSeries()
            ->where('frequency', TimeSeriesFrequencyEnum::YEARLY)
            ->first();

        if (!$yearlyTimeSeries) {
            return [
                'all_sales_since' => null,
                'total_sales' => 0,
                'total_invoices' => 0,
            ];
        }

        $allRecords = $yearlyTimeSeries->records()->orderBy('from', 'asc')->get();

        if ($allRecords->isEmpty()) {
            return [
                'all_sales_since' => null,
                'total_sales' => 0,
                'total_invoices' => 0,
            ];
        }

        return [
            'all_sales_since' => Carbon::parse($allRecords->first()->from)->toDateString(),
            'total_sales' => (float) $allRecords->sum('sales_grp_currency'),
            'total_invoices' => (int) $allRecords->sum('invoices'),
        ];
    }

    protected function getCustomerMetrics(Model $model, callable $queryBuilder): array
    {
        $totalCustomers = $model->stats->number_customers ?? 0;

        if ($totalCustomers === 0) {
            return [
                'total_customers' => 0,
                'repeat_customers' => 0,
                'repeat_customers_percentage' => 0,
            ];
        }

        $repeatCustomers = $queryBuilder($model);

        return [
            'total_customers' => $totalCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_customers_percentage' => ($repeatCustomers / $totalCustomers) * 100,
        ];
    }

    protected function getTimeSeriesForeignKey(string $timeSeriesRecordsTable): string
    {
        // Extract model name from table name
        // e.g., 'product_category_time_series_records' -> 'product_category_time_series_id'
        return str_replace('_records', '_id', $timeSeriesRecordsTable);
    }

    protected function emptyTimeSeriesResponse(string $currency): array
    {
        return [
            'all_sales_since' => null,
            'total_sales' => 0,
            'total_invoices' => 0,
            'customer_metrics' => [
                'total_customers' => 0,
                'repeat_customers' => 0,
                'repeat_customers_percentage' => 0,
            ],
            'yearly_sales' => [],
            'quarterly_sales' => [],
            'currency' => $currency,
        ];
    }
}
