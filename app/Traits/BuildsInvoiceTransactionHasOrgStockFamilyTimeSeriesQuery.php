<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait BuildsInvoiceTransactionHasOrgStockFamilyTimeSeriesQuery
{
    protected function fullInvoiceTransactionSelects(): array
    {
        return [
            DB::raw('SUM(net_amount) as sales_external'),
            DB::raw('SUM(org_net_amount) as sales_org_currency_external'),
            DB::raw('SUM(grp_net_amount) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN is_refund = true THEN net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN is_refund = true THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN is_refund = true THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN id END) as refunds'),
            DB::raw('COUNT(DISTINCT order_id) as orders'),
        ];
    }

    protected function applyFrequencyGrouping(Builder $query, TimeSeriesFrequencyEnum $frequency): Builder
    {
        $baseSelects = $this->fullInvoiceTransactionSelects();

        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY => $query
                ->select([DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date) as year'), ...$baseSelects])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date)')),

            TimeSeriesFrequencyEnum::QUARTERLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date) as year'),
                    DB::raw('EXTRACT(QUARTER FROM invoice_transaction_has_org_stocks.date) as quarter'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date)'), DB::raw('EXTRACT(QUARTER FROM invoice_transaction_has_org_stocks.date)')),

            TimeSeriesFrequencyEnum::MONTHLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date) as year'),
                    DB::raw('EXTRACT(MONTH FROM invoice_transaction_has_org_stocks.date) as month'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date)'), DB::raw('EXTRACT(MONTH FROM invoice_transaction_has_org_stocks.date)')),

            TimeSeriesFrequencyEnum::WEEKLY => $query
                ->select([
                    DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date) as year'),
                    DB::raw('EXTRACT(WEEK FROM invoice_transaction_has_org_stocks.date) as week'),
                    ...$baseSelects,
                ])
                ->groupBy(DB::raw('EXTRACT(YEAR FROM invoice_transaction_has_org_stocks.date)'), DB::raw('EXTRACT(WEEK FROM invoice_transaction_has_org_stocks.date)')),

            TimeSeriesFrequencyEnum::DAILY => $query
                ->select([DB::raw('CAST(invoice_transaction_has_org_stocks.date AS DATE) as date'), ...$baseSelects])
                ->groupBy(DB::raw('CAST(invoice_transaction_has_org_stocks.date AS DATE)')),
        };
    }
}
