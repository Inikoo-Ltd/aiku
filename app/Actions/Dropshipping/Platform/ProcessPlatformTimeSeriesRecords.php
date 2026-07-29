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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPlatformTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $platformId, int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$platformId:$shopId:$frequency->value:$from:$to";
    }

    public function handle(int $platformId, int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

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

        $metricsByPeriod = $this->getPlatformMetricsByPeriod($timeSeries, $shop, $timeSeries->frequency, $from, $to);

        $query = DB::connection('aiku_no_sticky')->table('invoices')
            ->where('invoices.platform_id', $timeSeries->platform_id)
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, customSelects: $this->platformInvoiceSelects())->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = [...$this->zeroMetrics(), ...($metricsByPeriod[$period]['metrics'] ?? [])];

            $timeSeries->records()->updateOrCreate(
                [
                    'platform_time_series_id' => $timeSeries->id,
                    'shop_id'                 => $shop->id,
                    'period'                  => $period,
                    'frequency'               => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'organisation_id'             => $shop->organisation_id,
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'invoices'                    => $result->invoices,
                    ...$metrics,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $shop, $metricsByPeriod, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(PlatformTimeSeries $timeSeries, Shop $shop, array $metricsByPeriod, array $processedPeriods): void
    {
        foreach ($metricsByPeriod as $period => $periodData) {
            if (in_array($period, $processedPeriods)) {
                continue;
            }

            $metrics = [...$this->zeroMetrics(), ...$periodData['metrics']];

            $hasActivity = collect($metrics)->some(fn ($value) => $value > 0);

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
                    'organisation_id'             => $shop->organisation_id,
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'invoices'                    => 0,
                    ...$metrics,
                ]
            );
        }
    }

    protected function getPlatformMetricsByPeriod(PlatformTimeSeries $timeSeries, Shop $shop, TimeSeriesFrequencyEnum $frequency, string $from, string $to): array
    {
        $channels = DB::connection('aiku_no_sticky')->table('customer_sales_channels')
            ->where('platform_id', $timeSeries->platform_id)
            ->where('shop_id', $shop->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->whereNull('deleted_at');

        $byPeriod = $this->mergeMetricsByPeriod([], $channels, $frequency, 'created_at', [
            DB::raw('count(*) as channels'),
        ]);

        $customers = DB::connection('aiku_no_sticky')->table('customer_sales_channels')
            ->leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
            ->where('customer_sales_channels.platform_id', $timeSeries->platform_id)
            ->where('customer_sales_channels.shop_id', $shop->id)
            ->where('customers.registered_at', '>=', $from)
            ->where('customers.registered_at', '<=', $to)
            ->whereNull('customers.deleted_at');

        $byPeriod = $this->mergeMetricsByPeriod($byPeriod, $customers, $frequency, 'customers.registered_at', [
            DB::raw('count(distinct customer_sales_channels.customer_id) as customers'),
        ]);

        $portfolios = DB::connection('aiku_no_sticky')->table('portfolios')
            ->where('portfolios.item_type', 'Product')
            ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
            ->where('portfolios.platform_id', $timeSeries->platform_id)
            ->where('portfolios.shop_id', $shop->id)
            ->where('portfolios.created_at', '>=', $from)
            ->where('portfolios.created_at', '<=', $to)
            ->where('portfolios.status', true)
            ->whereNull('portfolios.last_removed_at');

        $byPeriod = $this->mergeMetricsByPeriod($byPeriod, $portfolios, $frequency, 'portfolios.created_at', [
            DB::raw('count(distinct portfolios.item_id) as portfolios'),
        ]);

        $customerClients = DB::connection('aiku_no_sticky')->table('customer_clients')
            ->where('platform_id', $timeSeries->platform_id)
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->whereNull('deleted_at');

        return $this->mergeMetricsByPeriod($byPeriod, $customerClients, $frequency, 'created_at', [
            DB::raw('count(*) as customer_clients'),
        ]);
    }

    protected function zeroMetrics(): array
    {
        return [
            'channels'         => 0,
            'customers'        => 0,
            'portfolios'       => 0,
            'customer_clients' => 0,
        ];
    }
}
