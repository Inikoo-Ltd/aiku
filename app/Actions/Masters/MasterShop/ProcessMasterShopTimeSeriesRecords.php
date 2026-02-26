<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterShopTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMasterShopTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;

    public function getJobUniqueId(int $masterShopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$masterShopId:$frequency->value:$from:$to";
    }

    public function handle(int $masterShopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $masterShop = MasterShop::find($masterShopId);

        if (!$masterShop) {
            return;
        }

        $timeSeries = MasterShopTimeSeries::where('master_shop_id', $masterShop->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $masterShop->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        MasterShopTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(MasterShopTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoices')
            ->where('invoices.master_shop_id', $timeSeries->master_shop_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, customSelects: $this->masterShopInvoiceSelects())->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getMasterShopPeriodMetrics($timeSeries->master_shop_id, $periodFrom, $periodTo);

            $timeSeries->records()->updateOrCreate(
                [
                    'master_shop_time_series_id'  => $timeSeries->id,
                    'period'                      => $period,
                    'frequency'                   => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                         => $periodFrom,
                    'to'                           => $periodTo,
                    'sales_grp_currency_external'  => $result->sales_grp_currency_external,
                    'lost_revenue_grp_currency'    => $result->lost_revenue_grp_currency,
                    'customers_invoiced'           => $result->customers_invoiced,
                    'invoices'                     => $result->invoices,
                    'refunds'                      => $result->refunds,
                    'orders'                       => $result->orders,
                    ...$metrics,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(MasterShopTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $metrics = $this->getMasterShopPeriodMetrics($timeSeries->master_shop_id, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'master_shop_time_series_id'  => $timeSeries->id,
                    'period'                      => $periodData['period'],
                    'frequency'                   => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_grp_currency_external' => 0,
                    'lost_revenue_grp_currency'   => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                    'orders'                      => 0,
                    ...$metrics,
                ]
            );
        }
    }

    protected function getMasterShopPeriodMetrics(int $masterShopId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $basketsCreated = DB::table('orders')
            ->where('master_shop_id', $masterShopId)
            ->where('state', OrderStateEnum::CREATING)
            ->where('created_at', '>=', $periodFrom)
            ->where('created_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->selectRaw('sum(grp_net_amount) as grp_net_amount')
            ->first();

        $basketsUpdated = DB::table('orders')
            ->where('master_shop_id', $masterShopId)
            ->where('state', OrderStateEnum::CREATING)
            ->where('updated_at', '>=', $periodFrom)
            ->where('updated_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->selectRaw('sum(grp_net_amount) as grp_net_amount')
            ->first();

        $deliveryNotes = DB::table('delivery_notes')
            ->join('delivery_note_order', 'delivery_notes.id', '=', 'delivery_note_order.delivery_note_id')
            ->join('orders', 'delivery_note_order.order_id', '=', 'orders.id')
            ->where('orders.master_shop_id', $masterShopId)
            ->where('delivery_notes.date', '>=', $periodFrom)
            ->where('delivery_notes.date', '<=', $periodTo)
            ->whereNull('delivery_notes.deleted_at')
            ->distinct()
            ->count('delivery_notes.id');

        $registrationsBase = DB::table('customers')
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customers.master_shop_id', $masterShopId)
            ->where('customers.registered_at', '>=', $periodFrom)
            ->where('customers.registered_at', '<=', $periodTo)
            ->whereNull('customers.deleted_at');

        $registrationsWithOrders    = (clone $registrationsBase)->where('customer_stats.number_orders', '>', 0)->count();
        $registrationsWithoutOrders = (clone $registrationsBase)->where('customer_stats.number_orders', '=', 0)->count();

        return [
            'baskets_created_grp_currency' => $basketsCreated->grp_net_amount,
            'baskets_updated_grp_currency' => $basketsUpdated->grp_net_amount,
            'delivery_notes'               => $deliveryNotes,
            'registrations_with_orders'    => $registrationsWithOrders,
            'registrations_without_orders' => $registrationsWithoutOrders,
        ];
    }
}
