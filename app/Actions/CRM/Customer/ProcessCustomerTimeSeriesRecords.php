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
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessCustomerTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $customerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$customerId:$frequency->value:$from:$to";
    }

    public function handle(int $customerId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $timeSeries = CustomerTimeSeries::where('customer_id', $customer->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $customer->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        CustomerTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(CustomerTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::connection('aiku_no_sticky')->table('invoices')
            ->where('invoices.customer_id', $timeSeries->customer_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getCustomerPeriodMetrics($timeSeries->customer_id, $periodFrom, $periodTo);

            $timeSeries->records()->updateOrCreate(
                [
                    'customer_time_series_id' => $timeSeries->id,
                    'period'                  => $period,
                    'frequency'               => $timeSeries->frequency->singleLetter(),
                ],
                [
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
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(CustomerTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $metrics = $this->getCustomerPeriodMetrics($timeSeries->customer_id, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'customer_time_series_id' => $timeSeries->id,
                    'period'                  => $periodData['period'],
                    'frequency'               => $timeSeries->frequency->singleLetter(),
                ],
                [
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
                ]
            );
        }
    }

    protected function getCustomerPeriodMetrics(int $customerId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $basketsCreated = DB::connection('aiku_no_sticky')->table('orders')
            ->where('customer_id', $customerId)
            ->where('state', OrderStateEnum::CREATING)
            ->where('created_at', '>=', $periodFrom)
            ->where('created_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
            ->first();

        $basketsUpdated = DB::connection('aiku_no_sticky')->table('orders')
            ->where('customer_id', $customerId)
            ->where('state', OrderStateEnum::CREATING)
            ->where('updated_at', '>=', $periodFrom)
            ->where('updated_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
            ->first();

        $orders = DB::connection('aiku_no_sticky')->table('orders')
            ->where('customer_id', $customerId)
            ->where('date', '>=', $periodFrom)
            ->where('date', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->count();

        $deliveryNotes = DB::connection('aiku_no_sticky')->table('delivery_notes')
            ->where('customer_id', $customerId)
            ->where('date', '>=', $periodFrom)
            ->where('date', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->count();

        return [
            'baskets_created'              => $basketsCreated->net_amount ?? 0,
            'baskets_created_org_currency' => $basketsCreated->org_net_amount ?? 0,
            'baskets_created_grp_currency' => $basketsCreated->grp_net_amount ?? 0,
            'baskets_updated'              => $basketsUpdated->net_amount ?? 0,
            'baskets_updated_org_currency' => $basketsUpdated->org_net_amount ?? 0,
            'baskets_updated_grp_currency' => $basketsUpdated->grp_net_amount ?? 0,
            'orders'                       => $orders,
            'delivery_notes'               => $deliveryNotes,
        ];
    }

    protected function applyFrequencyGrouping(Builder $query, TimeSeriesFrequencyEnum $frequency): Builder
    {
        $selects = [
            DB::raw('SUM(CASE WHEN type = \'invoice\' THEN net_amount ELSE 0 END) as sales'),
            DB::raw('SUM(CASE WHEN type = \'invoice\' THEN org_net_amount ELSE 0 END) as sales_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'invoice\' THEN grp_net_amount ELSE 0 END) as sales_grp_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
        ];

        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY => $query
                ->select([DB::raw('EXTRACT(YEAR FROM invoices.date) as year'), ...$selects])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)')),

            TimeSeriesFrequencyEnum::QUARTERLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                    DB::raw('EXTRACT(QUARTER FROM invoices.date) as quarter'),
                    ...$selects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(QUARTER FROM invoices.date)')),

            TimeSeriesFrequencyEnum::MONTHLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                    DB::raw('EXTRACT(MONTH FROM invoices.date) as month'),
                    ...$selects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(MONTH FROM invoices.date)')),

            TimeSeriesFrequencyEnum::WEEKLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                    DB::raw('EXTRACT(WEEK FROM invoices.date) as week'),
                    ...$selects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(WEEK FROM invoices.date)')),

            TimeSeriesFrequencyEnum::DAILY => $query
                ->select([DB::raw('CAST(invoices.date AS DATE) as date'), ...$selects])
                ->groupBy(DB::raw('CAST(invoices.date AS DATE)')),
        };
    }
}
