<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopTimeSeries;
use App\Traits\BuildsInvoiceTimeSeriesQuery;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessShopTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTimeSeriesQuery;

    public function getJobUniqueId(int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$shopId:$frequency->value:$from:$to";
    }

    public function handle(int $shopId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

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

        $query = DB::table('invoices')
            ->where('invoices.shop_id', $timeSeries->shop_id)
            ->where('invoices.in_process', false)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoices.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency, includeOrders: true)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getShopPeriodMetrics($timeSeries->shop_id, $periodFrom, $periodTo);

            $timeSeries->records()->updateOrCreate(
                [
                    'shop_time_series_id' => $timeSeries->id,
                    'period'              => $period,
                    'frequency'           => $timeSeries->frequency->singleLetter(),
                ],
                [
                    'from'                         => $periodFrom,
                    'to'                           => $periodTo,
                    'sales'                        => $result->sales,
                    'sales_org_currency'           => $result->sales_org_currency,
                    'sales_grp_currency'           => $result->sales_grp_currency,
                    'lost_revenue'                 => $result->lost_revenue,
                    'lost_revenue_org_currency'    => $result->lost_revenue_org_currency,
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

    protected function processPeriodsWithoutInvoices(ShopTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $metrics = $this->getShopPeriodMetrics($timeSeries->shop_id, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'shop_time_series_id' => $timeSeries->id,
                    'period'              => $periodData['period'],
                    'frequency'           => $timeSeries->frequency->singleLetter(),
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
                    'customers_invoiced'        => 0,
                    'invoices'                  => 0,
                    'refunds'                   => 0,
                    'orders'                    => 0,
                    ...$metrics,
                ]
            );
        }
    }

    protected function getShopPeriodMetrics(int $shopId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $basketsCreated = DB::table('orders')
            ->where('shop_id', $shopId)
            ->where('state', OrderStateEnum::CREATING)
            ->where('created_at', '>=', $periodFrom)
            ->where('created_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
            ->first();

        $basketsUpdated = DB::table('orders')
            ->where('shop_id', $shopId)
            ->where('state', OrderStateEnum::CREATING)
            ->where('updated_at', '>=', $periodFrom)
            ->where('updated_at', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
            ->first();

        $deliveryNotes = DB::table('delivery_notes')
            ->where('shop_id', $shopId)
            ->where('date', '>=', $periodFrom)
            ->where('date', '<=', $periodTo)
            ->whereNull('deleted_at')
            ->count();

        $registrationsBase = DB::table('customers')
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customers.shop_id', $shopId)
            ->where('customers.registered_at', '>=', $periodFrom)
            ->where('customers.registered_at', '<=', $periodTo)
            ->whereNull('customers.deleted_at');

        $registrationsWithOrders    = (clone $registrationsBase)->where('customer_stats.number_orders', '>', 0)->count();
        $registrationsWithoutOrders = (clone $registrationsBase)->where('customer_stats.number_orders', '=', 0)->count();

        return [
            'baskets_created'              => $basketsCreated->net_amount,
            'baskets_created_org_currency' => $basketsCreated->org_net_amount,
            'baskets_created_grp_currency' => $basketsCreated->grp_net_amount,
            'baskets_updated'              => $basketsUpdated->net_amount,
            'baskets_updated_org_currency' => $basketsUpdated->org_net_amount,
            'baskets_updated_grp_currency' => $basketsUpdated->grp_net_amount,
            'delivery_notes'               => $deliveryNotes,
            'registrations_with_orders'    => $registrationsWithOrders,
            'registrations_without_orders' => $registrationsWithoutOrders,
        ];
    }
}
