<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessShopTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

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

        $results = DB::table('invoices')
            ->where('invoices.shop_id', $timeSeries->shop_id)
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
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('EXTRACT(QUARTER FROM invoices.date) as quarter'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(QUARTER FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('EXTRACT(MONTH FROM invoices.date) as month'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(MONTH FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('EXTRACT(WEEK FROM invoices.date) as week'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(WEEK FROM invoices.date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
            $results->select(
                DB::raw('CAST(invoices.date AS DATE) as date'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
                DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('CAST(invoices.date AS DATE)'));
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

            $basketsCreated = DB::table('orders')
                ->where('shop_id', $timeSeries->shop_id)
                ->where('state', OrderStateEnum::CREATING)
                ->where('created_at', '>=', $periodFrom)
                ->where('created_at', '<=', $periodTo)
                ->whereNull('deleted_at')
                ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
                ->first();

            $basketsUpdated = DB::table('orders')
                ->where('shop_id', $timeSeries->shop_id)
                ->where('state', OrderStateEnum::CREATING)
                ->where('updated_at', '>=', $periodFrom)
                ->where('updated_at', '<=', $periodTo)
                ->whereNull('deleted_at')
                ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
                ->first();

            $deliveryNotes = DB::table('delivery_notes')
                ->where('shop_id', $timeSeries->shop_id)
                ->where('date', '>=', $periodFrom)
                ->where('date', '<=', $periodTo)
                ->whereNull('deleted_at')
                ->count();

            $registrationsWithOrders = DB::table('customers')
                ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
                ->where('customers.shop_id', $timeSeries->shop_id)
                ->where('customers.registered_at', '>=', $periodFrom)
                ->where('customers.registered_at', '<=', $periodTo)
                ->where('customer_stats.number_orders', '>', 0)
                ->whereNull('customers.deleted_at')
                ->count();

            $registrationsWithoutOrders = DB::table('customers')
                ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
                ->where('customers.shop_id', $timeSeries->shop_id)
                ->where('customers.registered_at', '>=', $periodFrom)
                ->where('customers.registered_at', '<=', $periodTo)
                ->where('customer_stats.number_orders', '=', 0)
                ->whereNull('customers.deleted_at')
                ->count();

            $timeSeries->records()->updateOrCreate(
                [
                    'shop_time_series_id'  => $timeSeries->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter()
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
                    'baskets_created'              => $basketsCreated->net_amount,
                    'baskets_created_org_currency' => $basketsCreated->org_net_amount,
                    'baskets_created_grp_currency' => $basketsCreated->grp_net_amount,
                    'baskets_updated'              => $basketsUpdated->net_amount,
                    'baskets_updated_org_currency' => $basketsUpdated->org_net_amount,
                    'baskets_updated_grp_currency' => $basketsUpdated->grp_net_amount,
                    'delivery_notes'               => $deliveryNotes,
                    'registrations_with_orders'    => $registrationsWithOrders,
                    'registrations_without_orders' => $registrationsWithoutOrders,
                    'customers_invoiced'           => $result->customers_invoiced,
                    'invoices'                     => $result->invoices,
                    'refunds'                      => $result->refunds,
                    'orders'                       => $result->orders,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(ShopTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = $this->getNonInvoicePeriods($timeSeries, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $periodFrom = $periodData['from'];
            $periodTo   = $periodData['to'];
            $period     = $periodData['period'];

            $basketsCreated = DB::table('orders')
                ->where('shop_id', $timeSeries->shop_id)
                ->where('state', OrderStateEnum::CREATING)
                ->where('created_at', '>=', $periodFrom)
                ->where('created_at', '<=', $periodTo)
                ->whereNull('deleted_at')
                ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
                ->first();

            $basketsUpdated = DB::table('orders')
                ->where('shop_id', $timeSeries->shop_id)
                ->where('state', OrderStateEnum::CREATING)
                ->where('updated_at', '>=', $periodFrom)
                ->where('updated_at', '<=', $periodTo)
                ->whereNull('deleted_at')
                ->selectRaw('sum(net_amount) as net_amount, sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
                ->first();

            $deliveryNotes = DB::table('delivery_notes')
                ->where('shop_id', $timeSeries->shop_id)
                ->where('date', '>=', $periodFrom)
                ->where('date', '<=', $periodTo)
                ->whereNull('deleted_at')
                ->count();

            $registrationsWithOrders = DB::table('customers')
                ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
                ->where('customers.shop_id', $timeSeries->shop_id)
                ->where('customers.registered_at', '>=', $periodFrom)
                ->where('customers.registered_at', '<=', $periodTo)
                ->where('customer_stats.number_orders', '>', 0)
                ->whereNull('customers.deleted_at')
                ->count();

            $registrationsWithoutOrders = DB::table('customers')
                ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
                ->where('customers.shop_id', $timeSeries->shop_id)
                ->where('customers.registered_at', '>=', $periodFrom)
                ->where('customers.registered_at', '<=', $periodTo)
                ->where('customer_stats.number_orders', '=', 0)
                ->whereNull('customers.deleted_at')
                ->count();

            $hasActivity = ($basketsCreated->net_amount ?? 0) != 0
                || ($basketsCreated->org_net_amount ?? 0) != 0
                || ($basketsCreated->grp_net_amount ?? 0) != 0
                || ($basketsUpdated->net_amount ?? 0) != 0
                || ($basketsUpdated->org_net_amount ?? 0) != 0
                || ($basketsUpdated->grp_net_amount ?? 0) != 0
                || $deliveryNotes > 0
                || $registrationsWithOrders > 0
                || $registrationsWithoutOrders > 0;

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'shop_time_series_id'  => $timeSeries->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                         => $periodFrom,
                    'to'                           => $periodTo,
                    'sales'                        => 0,
                    'sales_org_currency'           => 0,
                    'sales_grp_currency'           => 0,
                    'lost_revenue'                 => 0,
                    'lost_revenue_org_currency'    => 0,
                    'lost_revenue_grp_currency'    => 0,
                    'baskets_created'              => $basketsCreated->net_amount,
                    'baskets_created_org_currency' => $basketsCreated->org_net_amount,
                    'baskets_created_grp_currency' => $basketsCreated->grp_net_amount,
                    'baskets_updated'              => $basketsUpdated->net_amount,
                    'baskets_updated_org_currency' => $basketsUpdated->org_net_amount,
                    'baskets_updated_grp_currency' => $basketsUpdated->grp_net_amount,
                    'delivery_notes'               => $deliveryNotes,
                    'registrations_with_orders'    => $registrationsWithOrders,
                    'registrations_without_orders' => $registrationsWithoutOrders,
                    'customers_invoiced'           => 0,
                    'invoices'                     => 0,
                    'refunds'                      => 0,
                    'orders'                       => 0,
                ]
            );
        }
    }

    protected function getNonInvoicePeriods(ShopTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): array
    {
        $periods = [];

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
