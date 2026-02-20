<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\InvoiceCategoryTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessInvoiceCategoryTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;

    public function getJobUniqueId(int $invoiceCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$invoiceCategoryId:$frequency->value:$from:$to";
    }

    public function handle(int $invoiceCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $invoiceCategory = InvoiceCategory::find($invoiceCategoryId);

        if (!$invoiceCategory) {
            return;
        }

        $timeSeries = InvoiceCategoryTimeSeries::where('invoice_category_id', $invoiceCategory->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $invoiceCategory->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        InvoiceCategoryTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(InvoiceCategoryTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoices')
            ->where('invoices.invoice_category_id', $timeSeries->invoice_category_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'invoice_category_time_series_id' => $timeSeries->id,
                    'period'                          => $period,
                    'frequency'                       => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'lost_revenue'                => $result->lost_revenue,
                    'lost_revenue_org_currency'   => $result->lost_revenue_org_currency,
                    'lost_revenue_grp_currency'   => $result->lost_revenue_grp_currency,
                    'customers_invoiced'          => $result->customers_invoiced,
                    'invoices'                    => $result->invoices,
                    'refunds'                     => $result->refunds,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(InvoiceCategoryTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'invoice_category_time_series_id' => $timeSeries->id,
                    'period'                          => $periodData['period'],
                    'frequency'                       => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'lost_revenue'                => 0,
                    'lost_revenue_org_currency'   => 0,
                    'lost_revenue_grp_currency'   => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                ]
            );
        }
    }
}
