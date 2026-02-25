<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel;

use App\Actions\Ordering\SalesChannel\Hydrators\SalesChannelTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Ordering\SalesChannel;
use App\Models\Ordering\SalesChannelTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSalesChannelTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;

    public function getJobUniqueId(int $salesChannelId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$salesChannelId:$frequency->value:$from:$to";
    }

    public function handle(int $salesChannelId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $salesChannel = SalesChannel::find($salesChannelId);

        if (!$salesChannel) {
            return;
        }

        $timeSeries = SalesChannelTimeSeries::where('sales_channel_id', $salesChannel->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $salesChannel->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        SalesChannelTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(SalesChannelTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoices')
            ->where('invoices.sales_channel_id', $timeSeries->sales_channel_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'sales_channel_time_series_id' => $timeSeries->id,
                    'period'                       => $period,
                    'frequency'                    => $timeSeries->frequency->singleLetter()
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

    protected function processPeriodsWithoutInvoices(SalesChannelTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'sales_channel_time_series_id' => $timeSeries->id,
                    'period'                       => $periodData['period'],
                    'frequency'                    => $timeSeries->frequency->singleLetter()
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
