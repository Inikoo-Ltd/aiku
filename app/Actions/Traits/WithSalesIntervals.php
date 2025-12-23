<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Dec 2024 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use Carbon\Carbon;
use DB;

trait WithSalesIntervals
{
    protected function getSalesIntervalsData(object $model, string $currency): array
    {
        $currentYear = Carbon::now()->year;
        $currentQuarter = 'Q' . Carbon::now()->quarter;

        if ($model instanceof Product) {
            return $this->getProductIntervalsData($model, $currency, $currentYear, $currentQuarter);
        }

        $salesIntervals = $this->getSalesIntervalsModel($model);

        $orderingIntervals = $this->getOrderingIntervalsModel($model);

        $data = [
            'currency' => $currency,
        ];

        if ($salesIntervals || $orderingIntervals) {
            $data['current_year'] = $this->getCurrentYearData(
                $salesIntervals,
                $orderingIntervals,
                $currentYear
            );
        }

        if ($salesIntervals || $orderingIntervals) {
            $data['current_quarter'] = $this->getCurrentQuarterData(
                $salesIntervals,
                $orderingIntervals,
                $currentYear,
                $currentQuarter
            );
        }

        if ($salesIntervals || $orderingIntervals) {
            $data['year_to_date'] = $this->getYearToDateData(
                $salesIntervals,
                $orderingIntervals
            );
        }

        return $data;
    }

    protected function getProductIntervalsData(Product $product, string $currency, int $currentYear, string $currentQuarter): array
    {
        $assetId = $product->id;

        if (!$assetId) {
            return ['currency' => $currency];
        }

        $salesIntervals = DB::table('asset_sales_intervals')
            ->where('asset_id', $assetId)
            ->first();

        $orderingIntervals = DB::table('asset_ordering_intervals')
            ->where('asset_id', $assetId)
            ->first();

        $data = [
            'currency' => $currency,
        ];

        if ($salesIntervals || $orderingIntervals) {
            $data['current_year'] = $this->getCurrentYearData(
                $salesIntervals,
                $orderingIntervals,
                $currentYear
            );

            $data['current_quarter'] = $this->getCurrentQuarterData(
                $salesIntervals,
                $orderingIntervals,
                $currentYear,
                $currentQuarter
            );

            $data['year_to_date'] = $this->getYearToDateData(
                $salesIntervals,
                $orderingIntervals
            );
        }

        return $data;
    }

    protected function getSalesIntervalsModel(object $model): ?object
    {
        if (method_exists($model, 'salesIntervals')) {
            return $model->salesIntervals;
        }

        return null;
    }

    protected function getOrderingIntervalsModel(object $model): ?object
    {
        if (method_exists($model, 'orderingIntervals')) {
            return $model->orderingIntervals;
        }

        return null;
    }

    protected function getCurrentYearData(?object $salesIntervals, ?object $orderingIntervals, int $year): array
    {
        $totalSales = 0;
        $previousYearSales = 0;

        if ($salesIntervals) {
            $totalSales = $salesIntervals->sales_1y ?? 0;
            $previousYearSales = $salesIntervals->sales_1y_ly ?? 0;
        }

        $totalInvoices = 0;
        $previousYearInvoices = 0;

        if ($orderingIntervals) {
            $totalInvoices = $orderingIntervals->invoices_1y ?? 0;
            $previousYearInvoices = $orderingIntervals->invoices_1y_ly ?? 0;
        }

        $salesDelta = $totalSales - $previousYearSales;
        $salesDeltaPercentage = $previousYearSales > 0
            ? (($salesDelta / $previousYearSales) * 100)
            : 0;

        $invoicesDelta = $totalInvoices - $previousYearInvoices;
        $invoicesDeltaPercentage = $previousYearInvoices > 0
            ? (($invoicesDelta / $previousYearInvoices) * 100)
            : 0;

        return [
            'period' => (string) $year,
            'total_sales' => (float) $totalSales,
            'total_invoices' => (int) $totalInvoices,
            'sales_delta' => (float) $salesDelta,
            'sales_delta_percentage' => (float) $salesDeltaPercentage,
            'previous_period_sales' => (float) $previousYearSales,
            'invoices_delta' => (int) $invoicesDelta,
            'invoices_delta_percentage' => (float) $invoicesDeltaPercentage,
            'previous_period_invoices' => (int) $previousYearInvoices,
        ];
    }

    protected function getCurrentQuarterData(?object $salesIntervals, ?object $orderingIntervals, int $year, string $quarter): array
    {
        $totalSales = 0;
        $previousQuarterSales = 0;

        if ($salesIntervals) {
            $totalSales = $salesIntervals->sales_1q ?? 0;
            $previousQuarterSales = $salesIntervals->sales_1q_ly ?? 0;
        }

        $totalInvoices = 0;
        $previousQuarterInvoices = 0;

        if ($orderingIntervals) {
            $totalInvoices = $orderingIntervals->invoices_1q ?? 0;
            $previousQuarterInvoices = $orderingIntervals->invoices_1q_ly ?? 0;
        }

        $salesDelta = $totalSales - $previousQuarterSales;
        $salesDeltaPercentage = $previousQuarterSales > 0
            ? (($salesDelta / $previousQuarterSales) * 100)
            : 0;

        $invoicesDelta = $totalInvoices - $previousQuarterInvoices;
        $invoicesDeltaPercentage = $previousQuarterInvoices > 0
            ? (($invoicesDelta / $previousQuarterInvoices) * 100)
            : 0;

        return [
            'period' => "$quarter $year",
            'total_sales' => (float) $totalSales,
            'total_invoices' => (int) $totalInvoices,
            'sales_delta' => (float) $salesDelta,
            'sales_delta_percentage' => (float) $salesDeltaPercentage,
            'previous_period_sales' => (float) $previousQuarterSales,
            'invoices_delta' => (int) $invoicesDelta,
            'invoices_delta_percentage' => (float) $invoicesDeltaPercentage,
            'previous_period_invoices' => (int) $previousQuarterInvoices,
        ];
    }

    protected function getYearToDateData(?object $salesIntervals, ?object $orderingIntervals): array
    {
        $totalSales = 0;
        $previousYtdSales = 0;

        if ($salesIntervals) {
            $totalSales = $salesIntervals->sales_ytd ?? 0;
            $previousYtdSales = $salesIntervals->sales_ytd_ly ?? 0;
        }

        $totalInvoices = 0;

        if ($orderingIntervals) {
            $totalInvoices = $orderingIntervals->invoices_ytd ?? 0;
        }

        $growthPercentage = $previousYtdSales > 0
            ? ((($totalSales - $previousYtdSales) / $previousYtdSales) * 100)
            : 0;

        return [
            'total_sales' => (float) $totalSales,
            'total_invoices' => (int) $totalInvoices,
            'growth_percentage' => (float) $growthPercentage,
        ];
    }
}
