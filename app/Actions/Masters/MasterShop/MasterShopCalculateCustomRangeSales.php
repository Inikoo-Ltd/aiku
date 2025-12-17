<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Wed, 17 Dec 2025 09:57:06 WITA
 * Location: Lembeng Beach, Bali, Indonesia
 */

namespace App\Actions\Masters\MasterShop;

use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterShopSalesMetrics;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopCalculateCustomRangeSales
{
    use AsAction;

    public function handle(MasterShop $masterShop, string $startDate, string $endDate): array
    {
        $stats = [];

        $stats = $this->calculateCurrentPeriodStats($masterShop, $stats, $startDate, $endDate);

        return $this->calculateLastYearStats($masterShop, $stats, $startDate, $endDate);
    }

    private function calculateCurrentPeriodStats(MasterShop $masterShop, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay();

        $metrics = MasterShopSalesMetrics::where('master_shop_id', $masterShop->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $stats['baskets_created_grp_currency_ctm'] = $metrics->sum('baskets_created_grp_currency');
        $stats['invoices_ctm'] = $metrics->sum('invoices');
        $stats['refunds_ctm'] = $metrics->sum('refunds');
        $stats['orders_ctm'] = $metrics->sum('orders');
        $stats['registrations_ctm'] = $metrics->sum('registrations');
        $stats['sales_grp_currency_ctm'] = $metrics->sum('sales_grp_currency');
        $stats['revenue_grp_currency_ctm'] = $metrics->sum('revenue_grp_currency');
        $stats['lost_revenue_grp_currency_ctm'] = $metrics->sum('lost_revenue_grp_currency');

        return $stats;
    }

    private function calculateLastYearStats(MasterShop $masterShop, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay()->subYear();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay()->subYear();

        $metrics = MasterShopSalesMetrics::where('master_shop_id', $masterShop->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $stats['baskets_created_grp_currency_ctm_ly'] = $metrics->sum('baskets_created_grp_currency');
        $stats['invoices_ctm_ly'] = $metrics->sum('invoices');
        $stats['refunds_ctm_ly'] = $metrics->sum('refunds');
        $stats['orders_ctm_ly'] = $metrics->sum('orders');
        $stats['registrations_ctm_ly'] = $metrics->sum('registrations');
        $stats['sales_grp_currency_ctm_ly'] = $metrics->sum('sales_grp_currency');
        $stats['revenue_grp_currency_ctm_ly'] = $metrics->sum('revenue_grp_currency');
        $stats['lost_revenue_grp_currency_ctm_ly'] = $metrics->sum('lost_revenue_grp_currency');

        return $stats;
    }
}
