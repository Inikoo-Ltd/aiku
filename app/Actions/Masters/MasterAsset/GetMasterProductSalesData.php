<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Dec 2024 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMasterProductSalesData extends GrpAction
{
    use AsAction;

    public function handle(MasterAsset $masterAsset): array
    {
        $productIds = $masterAsset->products()->pluck('id')->toArray();

        if (empty($productIds)) {
            return $this->getEmptyData();
        }

        $morphType = (new Product())->getMorphClass();

        // Base query for invoice transactions
        $baseQuery = InvoiceTransaction::query()
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.in_process', false)
            ->where('invoice_transactions.model_type', $morphType)
            ->whereIn('invoice_transactions.model_id', $productIds);

        return [
            'all_sales_since'           => $this->getFirstInvoiceDate($baseQuery),
            'total_sales'               => $this->getTotalSales($baseQuery),
            'total_invoices'            => $this->getTotalInvoices($baseQuery),
            'customer_metrics'          => $this->getCustomerMetrics($baseQuery),
            'yearly_sales'              => $this->getYearlySales($baseQuery),
            'quarterly_sales'           => $this->getQuarterlySales($baseQuery),
            'currency'                  => $masterAsset->group->currency->code ?? 'GBP',
        ];
    }

    private function getEmptyData(): array
    {
        return [
            'all_sales_since'           => null,
            'total_sales'               => 0,
            'total_invoices'            => 0,
            'customer_metrics'          => [
                'total_customers'       => 0,
                'repeat_customers'      => 0,
                'repeat_customers_percentage' => 0,
            ],
            'yearly_sales'              => [],
            'quarterly_sales'           => [],
            'currency'                  => 'GBP',
        ];
    }

    private function getFirstInvoiceDate($baseQuery): ?string
    {
        $firstInvoice = (clone $baseQuery)
            ->select('invoices.date')
            ->orderBy('invoices.date', 'asc')
            ->first();

        return $firstInvoice?->date?->format('Y-m-d');
    }

    private function getTotalSales($baseQuery): float
    {
        // Using grp_net_amount since parent is MasterShop which belongs to Group
        $result = (clone $baseQuery)
            ->select(DB::raw('SUM(invoice_transactions.grp_net_amount) as total'))
            ->first();

        return (float) ($result->total ?? 0);
    }

    private function getTotalInvoices($baseQuery): int
    {
        return (clone $baseQuery)
            ->distinct('invoices.id')
            ->count('invoices.id');
    }

    private function getCustomerMetrics($baseQuery): array
    {
        // Get total unique customers
        $totalCustomers = (clone $baseQuery)
            ->distinct('invoices.customer_id')
            ->count('invoices.customer_id');

        // Get repeat customers (customers with more than 1 invoice)
        $repeatCustomers = (clone $baseQuery)
            ->select('invoices.customer_id')
            ->groupBy('invoices.customer_id')
            ->havingRaw('COUNT(DISTINCT invoices.id) > 1')
            ->get()
            ->count();

        $repeatPercentage = $totalCustomers > 0
            ? round(($repeatCustomers / $totalCustomers) * 100, 2)
            : 0;

        return [
            'total_customers'              => $totalCustomers,
            'repeat_customers'             => $repeatCustomers,
            'repeat_customers_percentage'  => $repeatPercentage,
        ];
    }

    private function getYearlySales($baseQuery): array
    {
        $yearlySales = [];
        $currentYear = now()->year;

        // Start with current year (index 0), then previous 4 years
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;

            // Current year data
            $currentYearQuery = (clone $baseQuery)
                ->whereBetween('invoices.date', [
                    now()->subYears($i)->startOfYear(),
                    now()->subYears($i)->endOfYear()
                ])
                ->select(
                    DB::raw('SUM(invoice_transactions.grp_net_amount) as total_sales'),
                    DB::raw('COUNT(DISTINCT invoices.id) as total_invoices')
                )
                ->first();

            $totalSales = (float) ($currentYearQuery->total_sales ?? 0);
            $totalInvoices = (int) ($currentYearQuery->total_invoices ?? 0);

            // Previous year data for comparison (year - 1)
            $previousYearQuery = (clone $baseQuery)
                ->whereBetween('invoices.date', [
                    now()->subYears($i + 1)->startOfYear(),
                    now()->subYears($i + 1)->endOfYear()
                ])
                ->select(
                    DB::raw('SUM(invoice_transactions.grp_net_amount) as total_sales'),
                    DB::raw('COUNT(DISTINCT invoices.id) as total_invoices')
                )
                ->first();

            $previousYearSales = (float) ($previousYearQuery->total_sales ?? 0);
            $previousYearInvoices = (int) ($previousYearQuery->total_invoices ?? 0);

            // Calculate sales delta
            $salesDelta = $totalSales - $previousYearSales;
            $salesDeltaPercentage = $previousYearSales > 0
                ? round(($salesDelta / $previousYearSales) * 100, 2)
                : ($totalSales > 0 ? 100 : 0);

            // Calculate invoices delta
            $invoicesDelta = $totalInvoices - $previousYearInvoices;
            $invoicesDeltaPercentage = $previousYearInvoices > 0
                ? round(($invoicesDelta / $previousYearInvoices) * 100, 2)
                : ($totalInvoices > 0 ? 100 : 0);

            $yearlySales[] = [
                'year'                          => $year,
                'total_sales'                   => $totalSales,
                'total_invoices'                => $totalInvoices,
                'sales_delta'                   => $salesDelta,
                'sales_delta_percentage'        => $salesDeltaPercentage,
                'previous_year_sales'           => $previousYearSales,
                'invoices_delta'                => $invoicesDelta,
                'invoices_delta_percentage'     => $invoicesDeltaPercentage,
                'previous_year_invoices'        => $previousYearInvoices,
            ];
        }

        return $yearlySales;
    }

    private function getQuarterlySales($baseQuery): array
    {
        $quarterlySales = [];

        // Start with current quarter (index 0), then previous 4 quarters
        for ($i = 0; $i < 5; $i++) {
            $quarterStart = now()->subQuarters($i)->startOfQuarter();
            $quarterEnd = now()->subQuarters($i)->endOfQuarter();
            $quarterNumber = $quarterStart->quarter;
            $year = $quarterStart->year;

            // Current quarter data
            $currentQuarterQuery = (clone $baseQuery)
                ->whereBetween('invoices.date', [$quarterStart, $quarterEnd])
                ->select(
                    DB::raw('SUM(invoice_transactions.grp_net_amount) as total_sales'),
                    DB::raw('COUNT(DISTINCT invoices.id) as total_invoices')
                )
                ->first();

            $totalSales = (float) ($currentQuarterQuery->total_sales ?? 0);
            $totalInvoices = (int) ($currentQuarterQuery->total_invoices ?? 0);

            // Previous year same quarter data for comparison
            $previousYearQuarterStart = $quarterStart->copy()->subYear();
            $previousYearQuarterEnd = $quarterEnd->copy()->subYear();

            $previousYearQuery = (clone $baseQuery)
                ->whereBetween('invoices.date', [$previousYearQuarterStart, $previousYearQuarterEnd])
                ->select(
                    DB::raw('SUM(invoice_transactions.grp_net_amount) as total_sales'),
                    DB::raw('COUNT(DISTINCT invoices.id) as total_invoices')
                )
                ->first();

            $previousYearSales = (float) ($previousYearQuery->total_sales ?? 0);
            $previousYearInvoices = (int) ($previousYearQuery->total_invoices ?? 0);

            // Calculate sales delta
            $salesDelta = $totalSales - $previousYearSales;
            $salesDeltaPercentage = $previousYearSales > 0
                ? round(($salesDelta / $previousYearSales) * 100, 2)
                : ($totalSales > 0 ? 100 : 0);

            // Calculate invoices delta
            $invoicesDelta = $totalInvoices - $previousYearInvoices;
            $invoicesDeltaPercentage = $previousYearInvoices > 0
                ? round(($invoicesDelta / $previousYearInvoices) * 100, 2)
                : ($totalInvoices > 0 ? 100 : 0);

            $quarterlySales[] = [
                'quarter'                       => "Q{$quarterNumber} {$year}",
                'quarter_number'                => $quarterNumber,
                'year'                          => $year,
                'total_sales'                   => $totalSales,
                'total_invoices'                => $totalInvoices,
                'sales_delta'                   => $salesDelta,
                'sales_delta_percentage'        => $salesDeltaPercentage,
                'previous_year_sales'           => $previousYearSales,
                'invoices_delta'                => $invoicesDelta,
                'invoices_delta_percentage'     => $invoicesDeltaPercentage,
                'previous_year_invoices'        => $previousYearInvoices,
            ];
        }

        return $quarterlySales;
    }
}
