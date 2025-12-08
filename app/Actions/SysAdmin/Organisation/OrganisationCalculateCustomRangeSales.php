<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 16:21:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\OrganisationSalesMetrics;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationCalculateCustomRangeSales
{
    use AsAction;

    public function handle(Organisation $organisation, string $startDate, string $endDate): array
    {
        $stats = [];

        $stats = $this->calculateCurrentPeriodStats($organisation, $stats, $startDate, $endDate);

        return $this->calculateLastYearStats($organisation, $stats, $startDate, $endDate);
    }

    private function calculateCurrentPeriodStats(Organisation $organisation, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay();

        // Get all metrics for the date range
        $metrics = OrganisationSalesMetrics::where('organisation_id', $organisation->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        // Basket calculations
        $stats['baskets_created_grp_currency_ctm'] = $metrics->sum('baskets_created_grp_currency');
        $stats['baskets_created_org_currency_ctm'] = $metrics->sum('baskets_created_org_currency');

        // Count calculations
        $stats['invoices_ctm'] = $metrics->sum('invoices');
        $stats['refunds_ctm'] = $metrics->sum('refunds');
        $stats['orders_ctm'] = $metrics->sum('orders');
        $stats['registrations_ctm'] = $metrics->sum('registrations');

        // Sales calculations
        $stats['sales_grp_currency_ctm'] = $metrics->sum('sales_grp_currency');
        $stats['sales_org_currency_ctm'] = $metrics->sum('sales_org_currency');

        // Revenue calculations
        $stats['revenue_grp_currency_ctm'] = $metrics->sum('revenue_grp_currency');
        $stats['revenue_org_currency_ctm'] = $metrics->sum('revenue_org_currency');

        // Lost revenue calculations
        $stats['lost_revenue_grp_currency_ctm'] = $metrics->sum('lost_revenue_grp_currency');
        $stats['lost_revenue_org_currency_ctm'] = $metrics->sum('lost_revenue_org_currency');

        return $stats;
    }

    private function calculateLastYearStats(Organisation $organisation, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay()->subYear();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay()->subYear();

        // Get all metrics for the last year date range
        $metrics = OrganisationSalesMetrics::where('organisation_id', $organisation->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        // Basket calculations (last year)
        $stats['baskets_created_grp_currency_ctm_ly'] = $metrics->sum('baskets_created_grp_currency');
        $stats['baskets_created_org_currency_ctm_ly'] = $metrics->sum('baskets_created_org_currency');

        // Count calculations (last year)
        $stats['invoices_ctm_ly'] = $metrics->sum('invoices');
        $stats['refunds_ctm_ly'] = $metrics->sum('refunds');
        $stats['orders_ctm_ly'] = $metrics->sum('orders');
        $stats['registrations_ctm_ly'] = $metrics->sum('registrations');

        // Sales calculations (last year)
        $stats['sales_grp_currency_ctm_ly'] = $metrics->sum('sales_grp_currency');
        $stats['sales_org_currency_ctm_ly'] = $metrics->sum('sales_org_currency');

        // Revenue calculations (last year)
        $stats['revenue_grp_currency_ctm_ly'] = $metrics->sum('revenue_grp_currency');
        $stats['revenue_org_currency_ctm_ly'] = $metrics->sum('revenue_org_currency');

        // Lost revenue calculations (last year)
        $stats['lost_revenue_grp_currency_ctm_ly'] = $metrics->sum('lost_revenue_grp_currency');
        $stats['lost_revenue_org_currency_ctm_ly'] = $metrics->sum('lost_revenue_org_currency');

        return $stats;
    }
}
