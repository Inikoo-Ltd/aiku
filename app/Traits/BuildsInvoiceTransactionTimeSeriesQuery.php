<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait BuildsInvoiceTransactionTimeSeriesQuery
{
    protected function fullInvoiceTransactionSelects(): array
    {
        return [
            DB::raw('SUM(invoice_transactions.net_amount) as sales_external'),
            DB::raw('SUM(invoice_transactions.org_net_amount) as sales_org_currency_external'),
            DB::raw('SUM(invoice_transactions.grp_net_amount) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
            DB::raw('CAST(SUM(CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.quantity ELSE 0 END) AS INTEGER) as sold'),
        ];
    }

    protected function pivotBasedSelects(string $pivot = 'pivot'): array
    {
        return [
            DB::raw("SUM({$pivot}.net_amount) as sales_external"),
            DB::raw("SUM({$pivot}.org_net_amount) as sales_org_currency_external"),
            DB::raw("SUM({$pivot}.grp_net_amount) as sales_grp_currency_external"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true THEN {$pivot}.net_amount ELSE 0 END) as lost_revenue"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true THEN {$pivot}.org_net_amount ELSE 0 END) as lost_revenue_org_currency"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true THEN {$pivot}.grp_net_amount ELSE 0 END) as lost_revenue_grp_currency"),
            DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
        ];
    }

    protected function distributedInvoiceSelects(): array
    {
        return [
            DB::raw('SUM(invoice_transactions.net_amount / bridge_counts.cnt) as sales_external'),
            DB::raw('SUM(invoice_transactions.org_net_amount / bridge_counts.cnt) as sales_org_currency_external'),
            DB::raw('SUM(invoice_transactions.grp_net_amount / bridge_counts.cnt) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.net_amount / bridge_counts.cnt ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.org_net_amount / bridge_counts.cnt ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.grp_net_amount / bridge_counts.cnt ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
        ];
    }

    protected function weightedOrgStockSelects(): array
    {
        $weight = "CASE WHEN product_weights.total_weight > 0"
            ." THEN (COALESCE(phos.quantity, 1) * COALESCE(os.unit_cost, 0)) / product_weights.total_weight"
            ." ELSE 1.0 / product_weights.stock_count END";

        return [
            DB::raw("SUM(invoice_transactions.net_amount * {$weight}) as sales_external"),
            DB::raw("SUM(invoice_transactions.org_net_amount * {$weight}) as sales_org_currency_external"),
            DB::raw("SUM(invoice_transactions.grp_net_amount * {$weight}) as sales_grp_currency_external"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.net_amount * {$weight} ELSE 0 END) as lost_revenue"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.org_net_amount * {$weight} ELSE 0 END) as lost_revenue_org_currency"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.grp_net_amount * {$weight} ELSE 0 END) as lost_revenue_grp_currency"),
            DB::raw('COUNT(DISTINCT invoice_transactions.customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT invoice_transactions.order_id) as orders'),
        ];
    }

    protected function orgStockProductWeightsSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('product_has_org_stocks as phos_w')
            ->join('org_stocks as os_w', 'os_w.id', '=', 'phos_w.org_stock_id')
            ->select(
                'phos_w.product_id',
                DB::raw('SUM(COALESCE(phos_w.quantity, 1) * COALESCE(os_w.unit_cost, 0)) as total_weight'),
                DB::raw('COUNT(*) as stock_count')
            )
            ->groupBy('phos_w.product_id');
    }

    protected function applyFrequencyGrouping(Builder $query, TimeSeriesFrequencyEnum $frequency, ?array $selects = null): Builder
    {
        $baseSelects = $selects ?? $this->fullInvoiceTransactionSelects();

        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY => $query
                ->select([DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'), ...$baseSelects])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)')),

            TimeSeriesFrequencyEnum::QUARTERLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                    DB::raw('EXTRACT(QUARTER FROM invoice_transactions.date) as quarter'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'), DB::raw('EXTRACT(QUARTER FROM invoice_transactions.date)')),

            TimeSeriesFrequencyEnum::MONTHLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                    DB::raw('EXTRACT(MONTH FROM invoice_transactions.date) as month'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'), DB::raw('EXTRACT(MONTH FROM invoice_transactions.date)')),

            TimeSeriesFrequencyEnum::WEEKLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoice_transactions.date) as year'),
                    DB::raw('EXTRACT(WEEK FROM invoice_transactions.date) as week'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transactions.date)'), DB::raw('EXTRACT(WEEK FROM invoice_transactions.date)')),

            TimeSeriesFrequencyEnum::DAILY => $query
                ->select([DB::raw('CAST(invoice_transactions.date AS DATE) as date'), ...$baseSelects])
                ->groupBy(DB::raw('CAST(invoice_transactions.date AS DATE)')),
        };
    }
}
