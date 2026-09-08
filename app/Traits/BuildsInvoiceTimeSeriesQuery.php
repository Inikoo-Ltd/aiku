<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait BuildsInvoiceTimeSeriesQuery
{
    protected function fullInvoiceSelects(bool $includeOrders = false): array
    {
        return [
            DB::raw('SUM(net_amount) as sales_external'),
            DB::raw('SUM(org_net_amount) as sales_org_currency_external'),
            DB::raw('SUM(grp_net_amount) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
            ...($includeOrders ? [DB::raw('COUNT(DISTINCT order_id) as orders')] : []),
        ];
    }

    protected function masterShopInvoiceSelects(): array
    {
        return [
            DB::raw('SUM(grp_net_amount) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
            DB::raw('COUNT(DISTINCT order_id) as orders'),
        ];
    }

    protected function organisationInvoiceSelects(): array
    {
        return [
            DB::raw('SUM(org_net_amount) as sales_org_currency_external'),
            DB::raw('SUM(grp_net_amount) as sales_grp_currency_external'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
            DB::raw('COUNT(DISTINCT order_id) as orders'),
        ];
    }

    protected function platformInvoiceSelects(): array
    {
        return [
            DB::raw('SUM(net_amount) as sales_external'),
            DB::raw('SUM(org_net_amount) as sales_org_currency_external'),
            DB::raw('SUM(grp_net_amount) as sales_grp_currency_external'),
            DB::raw("COUNT(CASE WHEN type = 'invoice' THEN id END) as invoices"),
        ];
    }

    protected function customerInvoiceSelects(): array
    {
        return [
            DB::raw('SUM(CASE WHEN type = \'invoice\' THEN net_amount ELSE 0 END) as sales'),
            DB::raw('SUM(CASE WHEN type = \'invoice\' THEN org_net_amount ELSE 0 END) as sales_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'invoice\' THEN grp_net_amount ELSE 0 END) as sales_grp_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN net_amount ELSE 0 END) as lost_revenue'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN org_net_amount ELSE 0 END) as lost_revenue_org_currency'),
            DB::raw('SUM(CASE WHEN type = \'refund\' THEN grp_net_amount ELSE 0 END) as lost_revenue_grp_currency'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'invoice\' THEN id END) as invoices'),
            DB::raw('COUNT(DISTINCT CASE WHEN type = \'refund\' THEN id END) as refunds'),
        ];
    }

    protected function applyFrequencyGrouping(Builder $query, TimeSeriesFrequencyEnum $frequency, bool $includeOrders = false, ?array $customSelects = null): Builder
    {
        return $this->applyFrequencyGroupingOn($query, $frequency, 'invoices.date', $customSelects ?? $this->fullInvoiceSelects($includeOrders));
    }

    protected function applyFrequencyGroupingOn(Builder $query, TimeSeriesFrequencyEnum $frequency, string $dateColumn, array $selects): Builder
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY => $query
                ->select([DB::raw("EXTRACT(YEAR FROM $dateColumn) as year"), ...$selects])
                ->groupBy(DB::raw("EXTRACT(YEAR FROM $dateColumn)")),

            TimeSeriesFrequencyEnum::QUARTERLY => $query
                ->select([
                    DB::raw("EXTRACT(YEAR FROM $dateColumn) as year"),
                    DB::raw("EXTRACT(QUARTER FROM $dateColumn) as quarter"),
                    ...$selects,
                ])
                ->groupBy(DB::raw("EXTRACT(YEAR FROM $dateColumn)"), DB::raw("EXTRACT(QUARTER FROM $dateColumn)")),

            TimeSeriesFrequencyEnum::MONTHLY => $query
                ->select([
                    DB::raw("EXTRACT(YEAR FROM $dateColumn) as year"),
                    DB::raw("EXTRACT(MONTH FROM $dateColumn) as month"),
                    ...$selects,
                ])
                ->groupBy(DB::raw("EXTRACT(YEAR FROM $dateColumn)"), DB::raw("EXTRACT(MONTH FROM $dateColumn)")),

            TimeSeriesFrequencyEnum::WEEKLY => $query
                ->select([
                    DB::raw("EXTRACT(ISOYEAR FROM $dateColumn) as year"),
                    DB::raw("EXTRACT(WEEK FROM $dateColumn) as week"),
                    ...$selects,
                ])
                ->groupBy(DB::raw("EXTRACT(ISOYEAR FROM $dateColumn)"), DB::raw("EXTRACT(WEEK FROM $dateColumn)")),

            TimeSeriesFrequencyEnum::DAILY => $query
                ->select([DB::raw("CAST($dateColumn AS DATE) as date"), ...$selects])
                ->groupBy(DB::raw("CAST($dateColumn AS DATE)")),
        };
    }

    /**
     * Run a grouped-by-period metrics query over the whole window and key the rows by period label.
     * Replaces one-query-per-period probing: the caller merges several of these maps and reads
     * them in memory instead of hitting the database for every calendar period.
     *
     * @return array<string, array{from: \Carbon\Carbon, to: \Carbon\Carbon, metrics: array<string, mixed>}>
     */
    protected function mergeMetricsByPeriod(array $byPeriod, Builder $query, TimeSeriesFrequencyEnum $frequency, string $dateColumn, array $selects): array
    {
        foreach ($this->applyFrequencyGroupingOn($query, $frequency, $dateColumn, $selects)->get() as $row) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($row, $frequency);

            if (!isset($byPeriod[$period])) {
                $byPeriod[$period] = ['from' => $periodFrom, 'to' => $periodTo, 'metrics' => []];
            }

            $metrics = (array) $row;
            unset($metrics['year'], $metrics['quarter'], $metrics['month'], $metrics['week'], $metrics['date']);

            $byPeriod[$period]['metrics'] = [...$byPeriod[$period]['metrics'], ...$metrics];
        }

        return $byPeriod;
    }
}
