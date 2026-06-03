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

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(?int $offerCampaignId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        if (!$offerCampaignId) {
            $offerCampaignId = 'empty';
        }

        return "$offerCampaignId:$frequency->value:$from:$to";
    }

    public function handle(?int $offerCampaignId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        if (!$offerCampaignId) {
            return;
        }

        $offerCampaign = OfferCampaign::find($offerCampaignId);

        if (!$offerCampaign) {
            return;
        }

        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

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

        $query = DB::connection('aiku_no_sticky')->table('invoice_transactions')
            ->join('invoice_transaction_has_offer_allowances as itoha', function ($join) use ($offerCampaignId) {
                $join->on('itoha.invoice_transaction_id', '=', 'invoice_transactions.id')
                    ->where('itoha.offer_campaign_id', $offerCampaignId);
            })
            ->where('invoice_transactions.date', '>=', $from)
            ->where('invoice_transactions.date', '<=', $to)
            ->whereNull('invoice_transactions.deleted_at');

        $discountSelects = [
            DB::raw('SUM(itoha.discounted_amount) as discount_amount_external'),
            DB::raw('SUM(itoha.discounted_amount * invoice_transactions.org_exchange) as discount_org_currency_external'),
            DB::raw('SUM(itoha.discounted_amount * invoice_transactions.grp_exchange) as discount_grp_currency_external'),
        ];

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, array_merge($this->fullInvoiceTransactionSelects(), $discountSelects))->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'offer_campaign_time_series_id' => $timeSeries->id,
                    'period'                        => $period,
                    'frequency'                     => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                           => $periodFrom,
                    'to'                             => $periodTo,
                    'sales_external'                 => $result->sales_external,
                    'sales_org_currency_external'    => $result->sales_org_currency_external,
                    'sales_grp_currency_external'    => $result->sales_grp_currency_external,
                    'customers_invoiced'             => $result->customers_invoiced,
                    'invoices'                       => $result->invoices,
                    'refunds'                        => $result->refunds,
                    'orders'                         => $result->orders,
                    'discount_amount_external'       => $result->discount_amount_external,
                    'discount_org_currency_external' => $result->discount_org_currency_external,
                    'discount_grp_currency_external' => $result->discount_grp_currency_external,
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
                    'from'                           => $periodData['from'],
                    'to'                             => $periodData['to'],
                    'sales_external'                 => 0,
                    'sales_org_currency_external'    => 0,
                    'sales_grp_currency_external'    => 0,
                    'customers_invoiced'             => 0,
                    'invoices'                       => 0,
                    'refunds'                        => 0,
                    'orders'                         => 0,
                    'discount_amount_external'       => 0,
                    'discount_org_currency_external' => 0,
                    'discount_grp_currency_external' => 0,
                ]
            );
        }
    }
}
