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
        $shop     = Shop::find($shopId);

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
        $processedPeriods = [];

        $results = DB::table('invoices')
            ->where('invoices.platform_id', $timeSeries->platform_id)
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('EXTRACT(QUARTER FROM invoices.date) as quarter'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(QUARTER FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('EXTRACT(MONTH FROM invoices.date) as month'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(MONTH FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('EXTRACT(WEEK FROM invoices.date) as week'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(WEEK FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
            $results->select(
                DB::raw('CAST(invoices.date AS DATE) as date'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices")
            )->groupBy(DB::raw('CAST(invoices.date AS DATE)'));
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

            [$channels, $customers, $portfolios, $customerClients] = $this->getPeriodMetrics($timeSeries, $shop, $periodFrom, $periodTo);

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

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $shop, $from, $to, $processedPeriods);
    }

    protected function getPeriodMetrics(PlatformTimeSeries $timeSeries, Shop $shop, Carbon $periodFrom, Carbon $periodTo): array
    {
        $channels = DB::table('customer_sales_channels')
            ->where('platform_id', $timeSeries->platform_id)
            ->where('shop_id', $shop->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('created_at', '>=', $periodFrom)
            ->where('created_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->count();

        $customers = DB::table('customer_sales_channels')
            ->leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
            ->where('customer_sales_channels.platform_id', $timeSeries->platform_id)
            ->where('customer_sales_channels.shop_id', $shop->id)
            ->where('customers.registered_at', '>=', $periodFrom)
            ->where('customers.registered_at', '<=', $periodTo)
            ->whereNull('customers.deleted_at')
            ->distinct('customer_sales_channels.customer_id')
            ->count('customer_sales_channels.customer_id');

        $portfolios = DB::table('portfolios')
            ->where('portfolios.item_type', 'Product')
            ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
            ->where('portfolios.platform_id', $timeSeries->platform_id)
            ->where('portfolios.shop_id', $shop->id)
            ->where('portfolios.created_at', '>=', $periodFrom)
            ->where('portfolios.created_at', '<=', $periodTo)
            ->where('portfolios.status', true)
            ->whereNull('portfolios.last_removed_at')
            ->distinct('portfolios.item_id')
            ->count('portfolios.item_id');

        $customerClients = DB::table('customer_clients')
            ->where('platform_id', $timeSeries->platform_id)
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $periodFrom)
            ->where('created_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->count();

        return [$channels, $customers, $portfolios, $customerClients];
    }

    protected function processPeriodsWithoutInvoices(PlatformTimeSeries $timeSeries, Shop $shop, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = $this->getNonInvoicePeriods($timeSeries, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $periodFrom = $periodData['from'];
            $periodTo   = $periodData['to'];
            $period     = $periodData['period'];

            [$channels, $customers, $portfolios, $customerClients] = $this->getPeriodMetrics($timeSeries, $shop, $periodFrom, $periodTo);

            $hasActivity = $channels > 0
                || $customers > 0
                || $portfolios > 0
                || $customerClients > 0;

            if (!$hasActivity) {
                continue;
            }

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
                    'sales'              => 0,
                    'sales_org_currency' => 0,
                    'sales_grp_currency' => 0,
                    'invoices'           => 0,
                    'channels'           => $channels,
                    'customers'          => $customers,
                    'portfolios'         => $portfolios,
                    'customer_clients'   => $customerClients,
                ]
            );
        }
    }

    protected function getNonInvoicePeriods(PlatformTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): array
    {
        $periods   = [];
        $startDate = Carbon::parse($from)->startOfDay();
        $endDate   = Carbon::parse($to)->endOfDay();

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $period = $current->format('Y-m-d');
                if (!in_array($period, $processedPeriods)) {
                    $periods[] = [
                        'from'   => $current->copy()->startOfDay(),
                        'to'     => $current->copy()->endOfDay(),
                        'period' => $period,
                    ];
                }
                $current->addDay();
            }
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $current = $startDate->copy()->startOfWeek();
            while ($current <= $endDate) {
                $period = $current->isoFormat('GGGG').' W'.str_pad($current->isoWeek(), 2, '0', STR_PAD_LEFT);
                if (!in_array($period, $processedPeriods)) {
                    $periods[] = [
                        'from'   => $current->copy()->startOfWeek(),
                        'to'     => $current->copy()->endOfWeek(),
                        'period' => $period,
                    ];
                }
                $current->addWeek();
            }
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $current = $startDate->copy()->startOfMonth();
            while ($current <= $endDate) {
                $period = $current->year.'-'.str_pad($current->month, 2, '0', STR_PAD_LEFT);
                if (!in_array($period, $processedPeriods)) {
                    $periods[] = [
                        'from'   => $current->copy()->startOfMonth(),
                        'to'     => $current->copy()->endOfMonth(),
                        'period' => $period,
                    ];
                }
                $current->addMonth();
            }
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $current = $startDate->copy()->startOfQuarter();
            while ($current <= $endDate) {
                $period = $current->year.' Q'.$current->quarter;
                if (!in_array($period, $processedPeriods)) {
                    $periods[] = [
                        'from'   => $current->copy()->startOfQuarter(),
                        'to'     => $current->copy()->endOfQuarter(),
                        'period' => $period,
                    ];
                }
                $current->addQuarter();
            }
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $current = $startDate->copy()->startOfYear();
            while ($current <= $endDate) {
                $period = (string) $current->year;
                if (!in_array($period, $processedPeriods)) {
                    $periods[] = [
                        'from'   => $current->copy()->startOfYear(),
                        'to'     => $current->copy()->endOfYear(),
                        'period' => $period,
                    ];
                }
                $current->addYear();
            }
        }

        return $periods;
    }
}
