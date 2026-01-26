<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformTimeSeriesHydrateNumberRecords;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPlatformTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $platformId, int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$platformId:$shopId:$frequency->value:$from:$to";
    }

    public function handle(int $platformId, int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $platform = Platform::find($platformId);
        $shop = Shop::find($shopId);

        if (!$platform && !$shop) {
            return;
        }

        $timeSeries = PlatformTimeSeries::where('platform_id', $platform->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $platform->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $shop, $from, $to);

        PlatformTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(PlatformTimeSeries $timeSeries, Shop $shop, string $from, string $to): void
    {
        $results = DB::table('invoices')
            ->where('in_process', false)
            ->where('shop_id', $shop->id)
            ->where('platform_id', $timeSeries->platform_id)
            ->whereBetween('date', [$from, $to]);

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('EXTRACT(QUARTER FROM date) as quarter'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(QUARTER FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('EXTRACT(MONTH FROM date) as month'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(MONTH FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('EXTRACT(WEEK FROM date) as week'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(WEEK FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
            $results->select(
                DB::raw('CAST(date AS DATE) as date'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('CAST(date AS DATE)'));
        }

        $results = $results->get();

        foreach ($results as $result) {
            if ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
                $periodFrom = Carbon::create((int) $result->year, ((int) $result->quarter - 1) * 3 + 1)->startOfQuarter();
                $periodTo   = Carbon::create((int) $result->year, ((int) $result->quarter - 1) * 3 + 1)->endOfQuarter();
                $period     = $result->year.' Q'.$result->quarter;
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
                $periodFrom = Carbon::create((int) $result->year, (int) $result->month)->startOfMonth();
                $periodTo   = Carbon::create((int) $result->year, (int) $result->month)->endOfMonth();
                $period     = $result->year.'-'.str_pad($result->month, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
                $periodFrom = Carbon::create((int) $result->year)->week((int) $result->week)->startOfWeek();
                $periodTo   = Carbon::create((int) $result->year)->week((int) $result->week)->endOfWeek();
                $period     = $result->year.' W'.str_pad($result->week, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
                $periodFrom = Carbon::parse($result->date)->startOfDay();
                $periodTo   = Carbon::parse($result->date)->endOfDay();
                $period     = Carbon::parse($result->date)->format('Y-m-d');
            } else {
                $periodFrom = Carbon::parse((int) $result->year.'-01-01');
                $periodTo   = Carbon::parse((int) $result->year.'-12-31');
                $period     = $result->year;
            }

            $channels = DB::table('customer_sales_channels')
                ->where('platform_id', $timeSeries->platform_id)
                ->where('shop_id', $shop->id)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->whereBetween('created_at', [$periodFrom, $periodTo])
                ->count();

            $customers = DB::table('customer_sales_channels')
                ->leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
                ->where('customer_sales_channels.platform_id', $timeSeries->platform_id)
                ->where('customer_sales_channels.shop_id', $shop->id)
                ->whereBetween('customer_sales_channels.created_at', [$periodFrom, $periodTo])
                ->distinct('customer_sales_channels.customer_id')
                ->count('customer_sales_channels.customer_id');

            $portfolios = DB::table('portfolios')
                ->where('portfolios.item_type', 'Product')
                ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
                ->where('portfolios.platform_id', $timeSeries->platform_id)
                ->where('portfolios.shop_id', $shop->id)
                ->whereBetween('portfolios.created_at', [$periodFrom, $periodTo])
                ->distinct('portfolios.item_id')
                ->count('portfolios.item_id');

            $customerClients = DB::table('customer_clients')
                ->where('platform_id', $timeSeries->platform_id)
                ->where('shop_id', $shop->id)
                ->whereBetween('created_at', [$periodFrom, $periodTo])
                ->count();

            $timeSeries->records()->updateOrCreate(
                [
                    'platform_time_series_id' => $timeSeries->id,
                    'shop_id'                 => $shop->id,
                    'period'                  => $period,
                    'frequency'               => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'organisation_id'    => $shop->organisation_id,
                    'from'               => $periodFrom,
                    'to'                 => $periodTo,
                    'sales'              => $result->sales,
                    'sales_org_currency' => $result->sales_org_currency,
                    'sales_grp_currency' => $result->sales_grp_currency,
                    'invoices'           => $result->invoices,
                    'channels'           => $channels,
                    'customers'          => $customers,
                    'portfolios'         => $portfolios,
                    'customer_clients'   => $customerClients,
                ]
            );
        }
    }
}
