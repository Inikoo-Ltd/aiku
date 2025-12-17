<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Tue, 17 Dec 2025 11:04:22 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformShopSalesMetrics;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformShopCalculateCustomRangeSales
{
    use AsAction;

    public function handle(Platform $platform, int $shopId, string $startDate, string $endDate): array
    {
        $stats = [];

        $stats = $this->calculateCurrentPeriodStats($platform, $shopId, $stats, $startDate, $endDate);

        return $this->calculateLastYearStats($platform, $shopId, $stats, $startDate, $endDate);
    }

    private function calculateCurrentPeriodStats(Platform $platform, int $shopId, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay();

        $metrics = PlatformShopSalesMetrics::where('platform_id', $platform->id)
            ->where('shop_id', $shopId)
            ->whereBetween('date', [$start, $end])
            ->get();

        $stats['invoices_ctm'] = $metrics->sum('invoices');
        $stats['new_channels_ctm'] = $metrics->sum('new_channels');
        $stats['new_customers_ctm'] = $metrics->sum('new_customers');
        $stats['new_portfolios_ctm'] = $metrics->sum('new_portfolios');
        $stats['new_customer_client_ctm'] = $metrics->sum('new_customer_client');
        $stats['sales_ctm'] = $metrics->sum('sales');
        $stats['sales_grp_currency_ctm'] = $metrics->sum('sales_grp_currency');
        $stats['sales_org_currency_ctm'] = $metrics->sum('sales_org_currency');

        return $stats;
    }

    private function calculateLastYearStats(Platform $platform, int $shopId, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay()->subYear();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay()->subYear();

        $metrics = PlatformShopSalesMetrics::where('platform_id', $platform->id)
            ->where('shop_id', $shopId)
            ->whereBetween('date', [$start, $end])
            ->get();

        $stats['invoices_ctm_ly'] = $metrics->sum('invoices');
        $stats['new_channels_ctm_ly'] = $metrics->sum('new_channels');
        $stats['new_customers_ctm_ly'] = $metrics->sum('new_customers');
        $stats['new_portfolios_ctm_ly'] = $metrics->sum('new_portfolios');
        $stats['new_customer_client_ctm_ly'] = $metrics->sum('new_customer_client');
        $stats['sales_ctm_ly'] = $metrics->sum('sales');
        $stats['sales_grp_currency_ctm_ly'] = $metrics->sum('sales_grp_currency');
        $stats['sales_org_currency_ctm_ly'] = $metrics->sum('sales_org_currency');

        return $stats;
    }
}
