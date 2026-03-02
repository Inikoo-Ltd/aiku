<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\Goods\TradeUnitFamily\Hydrators\TradeUnitFamilyTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Goods\TradeUnitFamilyTimeSeries;
use App\Traits\BuildsInvoiceTransactionHasTradeUnitTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessTradeUnitFamilyTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionHasTradeUnitTimeSeriesQuery;

    public function getJobUniqueId(int $tradeUnitFamilyId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$tradeUnitFamilyId:$frequency->value:$from:$to";
    }

    public function handle(int $tradeUnitFamilyId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $tradeUnitFamily = TradeUnitFamily::find($tradeUnitFamilyId);

        if (!$tradeUnitFamily) {
            return;
        }

        $timeSeries = TradeUnitFamilyTimeSeries::where('trade_unit_family_id', $tradeUnitFamily->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $tradeUnitFamily->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        TradeUnitFamilyTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(TradeUnitFamilyTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoice_transaction_has_trade_units')
            ->where('trade_unit_family_id', $timeSeries->trade_unit_family_id)
            ->where('in_process', false)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to);

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'trade_unit_family_time_series_id' => $timeSeries->id,
                    'period'                           => $period,
                    'frequency'                        => $timeSeries->frequency->singleLetter(),
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

    protected function processPeriodsWithoutInvoices(TradeUnitFamilyTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'trade_unit_family_time_series_id' => $timeSeries->id,
                    'period'                           => $periodData['period'],
                    'frequency'                        => $timeSeries->frequency->singleLetter(),
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
