<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferCampaignTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOfferCampaignTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public function getJobUniqueId(int $offerCampaignId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$offerCampaignId:$frequency->value:$from:$to";
    }

    public function handle(int $offerCampaignId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $offerCampaign = OfferCampaign::find($offerCampaignId);

        if (!$offerCampaign) {
            return;
        }

        $timeSeries = OfferCampaignTimeSeries::where('offer_campaign_id', $offerCampaign->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $offerCampaign->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to, $offerCampaign->id);

        OfferCampaignTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(OfferCampaignTimeSeries $timeSeries, string $from, string $to, int $offerCampaignId): void
    {
        $processedPeriods = [];

        $query = DB::table('invoice_transactions')
            ->whereExists(function ($query) use ($offerCampaignId) {
                $query->select(DB::raw(1))
                      ->from('transaction_has_offer_allowances')
                      ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                      ->where('transaction_has_offer_allowances.offer_campaign_id', $offerCampaignId);
            })
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'offer_campaign_time_series_id' => $timeSeries->id,
                    'period'                        => $period,
                    'frequency'                     => $timeSeries->frequency->singleLetter()
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

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(OfferCampaignTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'offer_campaign_time_series_id' => $timeSeries->id,
                    'period'                        => $periodData['period'],
                    'frequency'                     => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                    'orders'                      => 0,
                ]
            );
        }
    }
}
