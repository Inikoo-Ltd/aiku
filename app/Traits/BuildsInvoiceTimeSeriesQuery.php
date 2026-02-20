<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait BuildsInvoiceTimeSeriesQuery
{
    protected function applyFrequencyGrouping(Builder $query, TimeSeriesFrequencyEnum $frequency, bool $includeOrders = false): Builder
    {
        $extraSelect = $includeOrders
            ? [DB::raw('COUNT(DISTINCT order_id) as orders')]
            : [];

        $baseSelects = [
            DB::raw('SUM(net_amount) as sales'),
            DB::raw('SUM(org_net_amount) as sales_org_currency'),
            DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
            ...$extraSelect,
        ];

        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY => $query
                ->select([DB::raw('EXTRACT(YEAR FROM invoices.date) as year'), ...$baseSelects])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)')),

            TimeSeriesFrequencyEnum::QUARTERLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                    DB::raw('EXTRACT(QUARTER FROM invoices.date) as quarter'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(QUARTER FROM invoices.date)')),

            TimeSeriesFrequencyEnum::MONTHLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                    DB::raw('EXTRACT(MONTH FROM invoices.date) as month'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(MONTH FROM invoices.date)')),

            TimeSeriesFrequencyEnum::WEEKLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoices.date) as year'),
                    DB::raw('EXTRACT(WEEK FROM invoices.date) as week'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoices.date)'), DB::raw('EXTRACT(WEEK FROM invoices.date)')),

            TimeSeriesFrequencyEnum::DAILY => $query
                ->select([DB::raw('CAST(invoices.date AS DATE) as date'), ...$baseSelects])
                ->groupBy(DB::raw('CAST(invoices.date AS DATE)')),
        };
    }
}
