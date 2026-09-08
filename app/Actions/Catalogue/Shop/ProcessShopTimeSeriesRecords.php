<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use App\Traits\UpsertsTimeSeriesRecords;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessShopTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;
    use UpsertsTimeSeriesRecords;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$shopId:$frequency->value:$from:$to";
    }

    public function handle(int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

        $shop = Shop::find($shopId);

        if (!$shop) {
            return;
        }

        $timeSeries = ShopTimeSeries::where('shop_id', $shop->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $shop->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        ShopTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(ShopTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];
        $rows             = [];

        $metricsByPeriod = $this->getShopMetricsByPeriod($timeSeries->shop_id, $timeSeries->frequency, $from, $to);

        $query = DB::connection('aiku_no_sticky')->table('invoices')
            ->where('invoices.shop_id', $timeSeries->shop_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, includeOrders: true)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = [...$this->zeroMetrics(), ...($metricsByPeriod[$period]['metrics'] ?? [])];

            $rows[] = [
                'shop_time_series_id' => $timeSeries->id,
                'period'              => $period,
                'frequency'           => $timeSeries->frequency->singleLetter(),
                ...[
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'lost_revenue'                => $result->lost_revenue,
                    'lost_revenue_org_currency'   => $result->lost_revenue_org_currency,
                    'lost_revenue_grp_currency'   => $result->lost_revenue_grp_currency,
                    'customers_invoiced'          => $result->customers_invoiced,
                    'invoices'                    => $result->invoices,
                    'refunds'                     => $result->refunds,
                    'orders'                      => $result->orders,
                    ...$metrics,
                ]
            ];

            $processedPeriods[] = $period;
        }

        $rows = [...$rows, ...$this->periodsWithoutInvoicesRows($timeSeries, $metricsByPeriod, $processedPeriods)];

        $this->syncTimeSeriesRecords($timeSeries, $rows, ['shop_time_series_id', 'period', 'frequency'], $from, $to);
    }

    protected function periodsWithoutInvoicesRows(ShopTimeSeries $timeSeries, array $metricsByPeriod, array $processedPeriods): array
    {
        $rows = [];

        foreach ($metricsByPeriod as $period => $periodData) {
            if (in_array($period, $processedPeriods)) {
                continue;
            }

            $metrics = [...$this->zeroMetrics(), ...$periodData['metrics']];

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $rows[] = [
                'shop_time_series_id' => $timeSeries->id,
                'period'              => $period,
                'frequency'           => $timeSeries->frequency->singleLetter(),
                ...[
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'lost_revenue'                => 0,
                    'lost_revenue_org_currency'   => 0,
                    'lost_revenue_grp_currency'   => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                    'orders'                      => 0,
                    ...$metrics,
                ]
            ];
        }

        return $rows;
    }

    protected function getShopMetricsByPeriod(int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): array
    {
        $registrations = DB::connection('aiku_no_sticky')->table('customers')
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customers.shop_id', $shopId)
            ->where('customers.registered_at', '>=', $from)
            ->where('customers.registered_at', '<=', $to)
            ->whereNull('customers.deleted_at');

        return $this->mergeMetricsByPeriod([], $registrations, $frequency, 'customers.registered_at', [
            DB::raw('count(case when customer_stats.number_orders > 0 then 1 end) as registrations_with_orders'),
            DB::raw('count(case when customer_stats.number_orders = 0 then 1 end) as registrations_without_orders'),
        ]);
    }

    protected function zeroMetrics(): array
    {
        return [
            'baskets_created'              => 0,
            'baskets_created_org_currency' => 0,
            'baskets_created_grp_currency' => 0,
            'baskets_updated'              => 0,
            'baskets_updated_org_currency' => 0,
            'baskets_updated_grp_currency' => 0,
            'delivery_notes'               => 0,
            'registrations_with_orders'    => 0,
            'registrations_without_orders' => 0,
        ];
    }
}
