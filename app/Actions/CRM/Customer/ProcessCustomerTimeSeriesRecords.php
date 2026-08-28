<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use App\Traits\UpsertsTimeSeriesRecords;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessCustomerTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;
    use UpsertsTimeSeriesRecords;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $customerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$customerId:$frequency->value:$from:$to";
    }

    public function handle(int $customerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

        $customer = Customer::on('aiku_no_sticky')->find($customerId);

        if (!$customer) {
            return;
        }

        $timeSeries = CustomerTimeSeries::on('aiku_no_sticky')->where('customer_id', $customer->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $customer->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        CustomerTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(CustomerTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $metricsByPeriod = $this->getCustomerMetricsByPeriod($timeSeries->customer_id, $timeSeries->frequency, $from, $to);

        $query = DB::connection('aiku_no_sticky')->table('invoices')
            ->where('invoices.customer_id', $timeSeries->customer_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, customSelects: $this->customerInvoiceSelects())->get();

        $rows = [];

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = [...$this->zeroMetrics(), ...($metricsByPeriod[$period]['metrics'] ?? [])];

            $rows[] = [
                'customer_time_series_id'   => $timeSeries->id,
                'period'                    => $period,
                'frequency'                 => $timeSeries->frequency->singleLetter(),
                'from'                      => $periodFrom,
                'to'                        => $periodTo,
                'sales'                     => $result->sales,
                'sales_org_currency'        => $result->sales_org_currency,
                'sales_grp_currency'        => $result->sales_grp_currency,
                'lost_revenue'              => $result->lost_revenue,
                'lost_revenue_org_currency' => $result->lost_revenue_org_currency,
                'lost_revenue_grp_currency' => $result->lost_revenue_grp_currency,
                'invoices'                  => $result->invoices,
                'refunds'                   => $result->refunds,
                ...$metrics,
            ];

            $processedPeriods[] = $period;
        }

        $rows = [...$rows, ...$this->periodsWithoutInvoicesRows($timeSeries, $metricsByPeriod, $processedPeriods)];

        $this->syncTimeSeriesRecords($timeSeries, $rows, ['customer_time_series_id', 'period', 'frequency'], $from, $to);
    }

    protected function periodsWithoutInvoicesRows(CustomerTimeSeries $timeSeries, array $metricsByPeriod, array $processedPeriods): array
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
                'customer_time_series_id'   => $timeSeries->id,
                'period'                    => $period,
                'frequency'                 => $timeSeries->frequency->singleLetter(),
                'from'                      => $periodData['from'],
                'to'                        => $periodData['to'],
                'sales'                     => 0,
                'sales_org_currency'        => 0,
                'sales_grp_currency'        => 0,
                'lost_revenue'              => 0,
                'lost_revenue_org_currency' => 0,
                'lost_revenue_grp_currency' => 0,
                'invoices'                  => 0,
                'refunds'                   => 0,
                ...$metrics,
            ];
        }

        return $rows;
    }

    protected function getCustomerMetricsByPeriod(int $customerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): array
    {
        $baskets = fn (string $dateColumn): Builder => DB::connection('aiku_no_sticky')->table('orders')
            ->where('customer_id', $customerId)
            ->where('state', OrderStateEnum::CREATING)
            ->where($dateColumn, '>=', $from)
            ->where($dateColumn, '<=', $to)
            ->whereNull('deleted_at');

        $byPeriod = $this->mergeMetricsByPeriod([], $baskets('created_at'), $frequency, 'created_at', [
            DB::raw('coalesce(sum(net_amount),0) as baskets_created'),
            DB::raw('coalesce(sum(org_net_amount),0) as baskets_created_org_currency'),
            DB::raw('coalesce(sum(grp_net_amount),0) as baskets_created_grp_currency'),
        ]);

        $byPeriod = $this->mergeMetricsByPeriod($byPeriod, $baskets('updated_at'), $frequency, 'updated_at', [
            DB::raw('coalesce(sum(net_amount),0) as baskets_updated'),
            DB::raw('coalesce(sum(org_net_amount),0) as baskets_updated_org_currency'),
            DB::raw('coalesce(sum(grp_net_amount),0) as baskets_updated_grp_currency'),
        ]);

        $orders = DB::connection('aiku_no_sticky')->table('orders')
            ->where('customer_id', $customerId)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $byPeriod = $this->mergeMetricsByPeriod($byPeriod, $orders, $frequency, 'date', [
            DB::raw('count(*) as orders'),
        ]);

        $deliveryNotes = DB::connection('aiku_no_sticky')->table('delivery_notes')
            ->where('customer_id', $customerId)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        return $this->mergeMetricsByPeriod($byPeriod, $deliveryNotes, $frequency, 'date', [
            DB::raw('count(*) as delivery_notes'),
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
            'orders'                       => 0,
            'delivery_notes'               => 0,
        ];
    }
}
