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
    use BuildsInvoiceTimeSeriesQuery {
        BuildsInvoiceTimeSeriesQuery::applyFrequencyGrouping as private applyInvoiceFrequencyGrouping;
    }

    protected function fullInvoiceTransactionSelects(): array
    {
        return [
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.net_amount ELSE 0 END) as sales_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.org_net_amount ELSE 0 END) as sales_org_currency_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.grp_net_amount ELSE 0 END) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.customer_id END) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.order_id END) as orders'),
            DB::raw('CAST(SUM(CASE WHEN invoice_transactions.is_refund = false AND invoice_transactions.is_partner = false THEN invoice_transactions.quantity ELSE 0 END) AS INTEGER) as sold'),
        ];
    }

    /**
     * Sales to our own organisations (partners), which every other select leaves out.
     */
    protected function partnerInvoiceTransactionSelects(): array
    {
        return [
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner THEN invoice_transactions.net_amount ELSE 0 END) as sales_internal'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner THEN invoice_transactions.org_net_amount ELSE 0 END) as sales_org_currency_internal'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner THEN invoice_transactions.grp_net_amount ELSE 0 END) as sales_grp_currency_internal'),
        ];
    }

    protected function pivotBasedSelects(string $pivot = 'pivot'): array
    {
        return [
            DB::raw('0 as sales_external'),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_partner = false THEN {$pivot}.org_net_amount ELSE 0 END) as sales_org_currency_external"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_partner = false THEN {$pivot}.grp_net_amount ELSE 0 END) as sales_grp_currency_external"),
            DB::raw('0 as lost_revenue'),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN {$pivot}.org_net_amount ELSE 0 END) as lost_revenue_org_currency"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN {$pivot}.grp_net_amount ELSE 0 END) as lost_revenue_grp_currency"),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.customer_id END) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.order_id END) as orders'),
        ];
    }

    protected function distributedInvoiceSelects(): array
    {
        return [
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.net_amount / bridge_counts.cnt ELSE 0 END) as sales_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.org_net_amount / bridge_counts.cnt ELSE 0 END) as sales_org_currency_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.grp_net_amount / bridge_counts.cnt ELSE 0 END) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.net_amount / bridge_counts.cnt ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.org_net_amount / bridge_counts.cnt ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.grp_net_amount / bridge_counts.cnt ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.customer_id END) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.order_id END) as orders'),
        ];
    }

    protected function weightedOrgStockSelects(): array
    {
        $weight = "CASE WHEN product_weights.total_weight > 0"
            ." THEN (COALESCE(phos.quantity, 1) * COALESCE(os.unit_cost, 0)) / product_weights.total_weight"
            ." ELSE 1.0 / product_weights.stock_count END";

        return [
            DB::raw("SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.net_amount * {$weight} ELSE 0 END) as sales_external"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.org_net_amount * {$weight} ELSE 0 END) as sales_org_currency_external"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.grp_net_amount * {$weight} ELSE 0 END) as sales_grp_currency_external"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.net_amount * {$weight} ELSE 0 END) as lost_revenue"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.org_net_amount * {$weight} ELSE 0 END) as lost_revenue_org_currency"),
            DB::raw("SUM(CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.grp_net_amount * {$weight} ELSE 0 END) as lost_revenue_grp_currency"),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.customer_id END) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = false AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_refund = true AND invoice_transactions.is_partner = false THEN invoice_transactions.invoice_id END) as refunds'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoice_transactions.is_partner = false THEN invoice_transactions.order_id END) as orders'),
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
        return $this->applyFrequencyGroupingOn($query, $frequency, 'invoice_transactions.date', $selects ?? $this->fullInvoiceTransactionSelects());
    }
}
