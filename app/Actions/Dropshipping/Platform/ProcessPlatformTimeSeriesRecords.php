<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformTimeSeriesHydrateNumberRecords;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPlatformTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;

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

        $query = DB::table('invoices')
            ->where('invoices.platform_id', $timeSeries->platform_id)
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, customSelects: $this->platformInvoiceSelects())->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getPlatformPeriodMetrics($timeSeries, $shop, $periodFrom, $periodTo);

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
                    ...$metrics,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $shop, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(PlatformTimeSeries $timeSeries, Shop $shop, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $metrics = $this->getPlatformPeriodMetrics($timeSeries, $shop, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value > 0);

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'platform_time_series_id' => $timeSeries->id,
                    'shop_id'                 => $shop->id,
                    'period'                  => $periodData['period'],
                    'frequency'               => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'organisation_id'    => $shop->organisation_id,
                    'from'               => $periodData['from'],
                    'to'                 => $periodData['to'],
                    'sales'              => 0,
                    'sales_org_currency' => 0,
                    'sales_grp_currency' => 0,
                    'invoices'           => 0,
                    ...$metrics,
                ]
            );
        }
    }

    protected function getPlatformPeriodMetrics(PlatformTimeSeries $timeSeries, Shop $shop, Carbon $periodFrom, Carbon $periodTo): array
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

        return [
            'channels'         => $channels,
            'customers'        => $customers,
            'portfolios'       => $portfolios,
            'customer_clients' => $customerClients,
        ];
    }
}
