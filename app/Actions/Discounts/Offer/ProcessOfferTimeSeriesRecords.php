<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\Offer\Hydrators\OfferTimeSeriesHydrateNumberRecords;
use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOfferTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $offerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$offerId:$frequency->value:$from:$to";
    }

    public function handle(int $offerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $offer = Offer::find($offerId);
        if (!$offer) {
            return;
        }

        $timeSeries = OfferTimeSeries::where('offer_id', $offer->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $offer->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to, $offer->id);

        OfferTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(OfferTimeSeries $timeSeries, string $from, string $to, int $offerId): void
    {
        $results = DB::table('invoice_transactions')
            ->join('transaction_has_offer_allowances', 'invoice_transactions.transaction_id', '=', 'transaction_has_offer_allowances.transaction_id')
            ->where('transaction_has_offer_allowances.offer_id', $offerId)
            ->where('invoice_transactions.date', '>=', $from)
            ->where('invoice_transactions.date', '<=', $to)
            ->whereNull('invoice_transactions.deleted_at');

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                DB::raw('SUM(invoice_transactions.net_amount) as sales'),
                DB::raw('SUM(invoice_transactions.org_net_amount) as sales_org_currency'),
                DB::raw('SUM(invoice_transactions.grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                DB::raw('EXTRACT(QUARTER FROM invoice_transactions.date) as quarter'),
                DB::raw('SUM(invoice_transactions.net_amount) as sales'),
                DB::raw('SUM(invoice_transactions.org_net_amount) as sales_org_currency'),
                DB::raw('SUM(invoice_transactions.grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'), DB::raw('EXTRACT(QUARTER FROM invoice_transactions.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                DB::raw('EXTRACT(MONTH FROM invoice_transactions.date) as month'),
                DB::raw('SUM(invoice_transactions.net_amount) as sales'),
                DB::raw('SUM(invoice_transactions.org_net_amount) as sales_org_currency'),
                DB::raw('SUM(invoice_transactions.grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'), DB::raw('EXTRACT(MONTH FROM invoice_transactions.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                DB::raw('EXTRACT(WEEK FROM invoice_transactions.date) as week'),
                DB::raw('SUM(invoice_transactions.net_amount) as sales'),
                DB::raw('SUM(invoice_transactions.org_net_amount) as sales_org_currency'),
                DB::raw('SUM(invoice_transactions.grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'), DB::raw('EXTRACT(WEEK FROM invoice_transactions.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
            $results->select(
                DB::raw('CAST(invoice_transactions.date AS DATE) as date'),
                DB::raw('SUM(invoice_transactions.net_amount) as sales'),
                DB::raw('SUM(invoice_transactions.org_net_amount) as sales_org_currency'),
                DB::raw('SUM(invoice_transactions.grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
            )->groupBy(DB::raw('CAST(invoice_transactions.date AS DATE)'));
        }

        $results = $results->get();

        foreach ($results as $result) {
            if ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
                $periodFrom = Carbon::create((int)$result->year, ((int)$result->quarter - 1) * 3 + 1)->startOfQuarter();
                $periodTo   = Carbon::create((int)$result->year, ((int)$result->quarter - 1) * 3 + 1)->endOfQuarter();
                $period     = $result->year.' Q'.$result->quarter;
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
                $periodFrom = Carbon::create((int)$result->year, (int)$result->month)->startOfMonth();
                $periodTo   = Carbon::create((int)$result->year, (int)$result->month)->endOfMonth();
                $period     = $result->year.'-'.str_pad($result->month, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
                $periodFrom = Carbon::create((int)$result->year)->week((int)$result->week)->startOfWeek();
                $periodTo   = Carbon::create((int)$result->year)->week((int)$result->week)->endOfWeek();
                $period     = $result->year.' W'.str_pad($result->week, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
                $periodFrom = Carbon::parse($result->date)->startOfDay();
                $periodTo   = Carbon::parse($result->date)->endOfDay();
                $period     = Carbon::parse($result->date)->format('Y-m-d');
            } else {
                $periodFrom = Carbon::parse((int)$result->year.'-01-01');
                $periodTo   = Carbon::parse((int)$result->year.'-12-31');
                $period     = $result->year;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'offer_time_series_id' => $timeSeries->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'               => $periodFrom,
                    'to'                 => $periodTo,
                    'sales'              => $result->sales,
                    'sales_org_currency' => $result->sales_org_currency,
                    'sales_grp_currency' => $result->sales_grp_currency,
                    'customers_invoiced' => $result->customers_invoiced,
                    'invoices'           => $result->invoices,
                    'refunds'            => $result->refunds,
                    'orders'             => $result->orders,
                ]
            );
        }
    }
}
