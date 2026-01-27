<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\OrganisationTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOrganisationTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $organisationId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$organisationId:$frequency->value:$from:$to";
    }

    public function handle(int $organisationId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $organisation = Organisation::find($organisationId);
        if (!$organisation) {
            return;
        }

        $timeSeries = OrganisationTimeSeries::where('organisation_id', $organisation->id)
            ->where('frequency', $frequency->value)->first();
        if (!$timeSeries) {
            $timeSeries = $organisation->timeSeries()->create([
                'frequency' => $frequency,
            ]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        OrganisationTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(OrganisationTimeSeries $timeSeries, string $from, string $to): void
    {
        $results = DB::table('invoices')
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->where('invoices.organisation_id', $timeSeries->organisation_id);

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
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
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
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
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
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
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
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
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
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
                ->where('organisation_id', $timeSeries->organisation_id)
                ->where('state', OrderStateEnum::CREATING)
                ->where('date', '>=', $periodFrom)
                ->where('date', '<=', $periodTo)
                ->selectRaw('sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
                ->first();

            $basketsUpdated = DB::table('orders')
                ->where('organisation_id', $timeSeries->organisation_id)
                ->where('state', OrderStateEnum::CREATING)
                ->where('updated_at', '>=', $periodFrom)
                ->where('updated_at', '<=', $periodTo)
                ->selectRaw('sum(org_net_amount) as org_net_amount, sum(grp_net_amount) as grp_net_amount')
                ->first();

            $deliveryNotes = DB::table('delivery_notes')
                ->where('organisation_id', $timeSeries->organisation_id)
                ->where('date', '>=', $periodFrom)
                ->where('date', '<=', $periodTo)
                ->count();

            $registrationsWithOrders = DB::table('customers')
                ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
                ->where('customers.organisation_id', $timeSeries->organisation_id)
                ->where('customers.registered_at', '>=', $periodFrom)
                ->where('customers.registered_at', '<=', $periodTo)
                ->where('customer_stats.number_orders', '>', 0)
                ->count();

            $registrationsWithoutOrders = DB::table('customers')
                ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
                ->where('customers.organisation_id', $timeSeries->organisation_id)
                ->where('customers.registered_at', '>=', $periodFrom)
                ->where('customers.registered_at', '<=', $periodTo)
                ->where('customer_stats.number_orders', '=', 0)
                ->count();

            $timeSeries->records()->updateOrCreate(
                [
                    'organisation_time_series_id' => $timeSeries->id,
                    'period'                      => $period,
                    'frequency'                   => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                         => $periodFrom,
                    'to'                           => $periodTo,
                    'sales_org_currency'           => $result->sales_org_currency,
                    'sales_grp_currency'           => $result->sales_grp_currency,
                    'lost_revenue_org_currency'    => $result->lost_revenue_org_currency,
                    'lost_revenue_grp_currency'    => $result->lost_revenue_grp_currency,
                    'baskets_created_org_currency' => $basketsCreated->org_net_amount,
                    'baskets_created_grp_currency' => $basketsCreated->grp_net_amount,
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
        }
    }
}
