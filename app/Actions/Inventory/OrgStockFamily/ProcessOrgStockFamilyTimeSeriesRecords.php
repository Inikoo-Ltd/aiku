<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\OrgStockFamilyTimeSeries;
use App\Traits\BuildsInvoiceTransactionHasOrgStockFamilyTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOrgStockFamilyTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionHasOrgStockFamilyTimeSeriesQuery;

    public function getJobUniqueId(int $orgStockFamilyId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$orgStockFamilyId:$frequency->value:$from:$to";
    }

    public function handle(int $orgStockFamilyId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $orgStockFamily = OrgStockFamily::find($orgStockFamilyId);

        if (!$orgStockFamily) {
            return;
        }

        $timeSeries = OrgStockFamilyTimeSeries::where('org_stock_family_id', $orgStockFamily->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $orgStockFamily->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        OrgStockFamilyTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(OrgStockFamilyTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoice_transaction_has_org_stocks')
            ->where('org_stock_family_id', $timeSeries->org_stock_family_id)
            ->where('in_process', false)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to);

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'org_stock_family_time_series_id' => $timeSeries->id,
                    'period'                          => $period,
                    'frequency'                       => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'sales_internal'              => 0,
                    'sales_org_currency_internal' => 0,
                    'sales_grp_currency_internal' => 0,
                    'lost_revenue'                => $result->lost_revenue,
                    'lost_revenue_org_currency'   => $result->lost_revenue_org_currency,
                    'lost_revenue_grp_currency'   => $result->lost_revenue_grp_currency,
                    'customers_invoiced'          => $result->customers_invoiced,
                    'invoices'                    => $result->invoices,
                    'refunds'                     => $result->refunds,
                    'orders'                      => $result->orders,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(OrgStockFamilyTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'org_stock_family_time_series_id' => $timeSeries->id,
                    'period'                          => $periodData['period'],
                    'frequency'                       => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'sales_internal'              => 0,
                    'sales_org_currency_internal' => 0,
                    'sales_grp_currency_internal' => 0,
                    'lost_revenue'                => 0,
                    'lost_revenue_org_currency'   => 0,
                    'lost_revenue_grp_currency'   => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                    'orders'                      => 0,
                ]
            );
        }
    }
}
