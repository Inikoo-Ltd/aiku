<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 16:21:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\InvoiceCategorySalesMetrics;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceCategoryCalculateCustomRangeSales
{
    use AsAction;

    public function handle(InvoiceCategory $invoiceCategory, string $startDate, string $endDate): array
    {
        $stats = [];

        $stats = $this->calculateCurrentPeriodStats($invoiceCategory, $stats, $startDate, $endDate);

        return $this->calculateLastYearStats($invoiceCategory, $stats, $startDate, $endDate);
    }

    private function calculateCurrentPeriodStats(InvoiceCategory $invoiceCategory, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay();

        // Get all metrics for the date range
        $metrics = InvoiceCategorySalesMetrics::where('invoice_category_id', $invoiceCategory->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $stats['invoices_ctm'] = $metrics->sum('invoices');
        $stats['refunds_ctm'] = $metrics->sum('refunds');
        $stats['sales_grp_currency_ctm'] = $metrics->sum('sales_grp_currency');
        $stats['sales_invoice_category_currency_ctm'] = $metrics->sum('sales_invoice_category_currency');
        $stats['revenue_grp_currency_ctm'] = $metrics->sum('revenue_grp_currency');
        $stats['revenue_invoice_category_currency_ctm'] = $metrics->sum('revenue_invoice_category_currency');
        $stats['lost_revenue_grp_currency_ctm'] = $metrics->sum('lost_revenue_grp_currency');
        $stats['lost_revenue_invoice_category_currency_ctm'] = $metrics->sum('lost_revenue_invoice_category_currency');

        return $stats;
    }

    private function calculateLastYearStats(InvoiceCategory $invoiceCategory, array $stats, string $startDate, string $endDate): array
    {
        $start = Carbon::createFromFormat('Ymd', $startDate)->startOfDay()->subYear();
        $end = Carbon::createFromFormat('Ymd', $endDate)->endOfDay()->subYear();

        // Get all metrics for the last year date range
        $metrics = InvoiceCategorySalesMetrics::where('invoice_category_id', $invoiceCategory->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $stats['invoices_ctm_ly'] = $metrics->sum('invoices');
        $stats['refunds_ctm_ly'] = $metrics->sum('refunds');
        $stats['sales_grp_currency_ctm_ly'] = $metrics->sum('sales_grp_currency');
        $stats['sales_invoice_category_currency_ctm_ly'] = $metrics->sum('sales_invoice_category_currency');
        $stats['revenue_grp_currency_ctm_ly'] = $metrics->sum('revenue_grp_currency');
        $stats['revenue_invoice_category_currency_ctm_ly'] = $metrics->sum('revenue_invoice_category_currency');
        $stats['lost_revenue_grp_currency_ctm_ly'] = $metrics->sum('lost_revenue_grp_currency');
        $stats['lost_revenue_invoice_category_currency_ctm_ly'] = $metrics->sum('lost_revenue_invoice_category_currency');

        return $stats;
    }
}
