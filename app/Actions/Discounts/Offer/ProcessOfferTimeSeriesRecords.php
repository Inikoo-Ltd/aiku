<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\Offer\Hydrators\OfferTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOfferTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(?int $offerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        if (!$offerId) {
            $offerId = 'empty';
        }

        return "$offerId:$frequency->value:$from:$to";
    }

    public function handle(?int $offerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        if (!$offerId) {
            return;
        }

        $offer = Offer::find($offerId);

        if (!$offer) {
            return;
        }

        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $timeSeries = OfferTimeSeries::where('offer_id', $offer->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $offer->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to, $offer->id);

        OfferTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(OfferTimeSeries $timeSeries, string $from, string $to, int $offerId): void
    {
        $processedPeriods = [];

        $query = DB::connection('aiku_no_sticky')->table('invoice_transactions')
            ->whereExists(function ($query) use ($offerId) {
                $query->select(DB::raw(1))
                    ->from('transaction_has_offer_allowances')
                    ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                    ->where('transaction_has_offer_allowances.offer_id', $offerId);
            })
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'offer_time_series_id' => $timeSeries->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'customers_invoiced'          => $result->customers_invoiced,
                    'invoices'                    => $result->invoices,
                    'refunds'                     => $result->refunds,
                    'orders'                      => $result->orders,
                ]
            );
        }
    }
}
