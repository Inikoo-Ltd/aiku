<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategoryTimeSeries;

use App\Actions\Catalogue\ProductCategoryTimeSeries\Hydrators\ProductCategoryTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\ProductCategoryTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessProductCategoryTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $productCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$productCategoryId:$frequency->value:$from:$to";
    }

    public function handle(int $productCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $productCategory = ProductCategory::find($productCategoryId);

        if (!$productCategory) {
            return;
        }

        $timeSeries = ProductCategoryTimeSeries::where('product_category_id', $productCategoryId)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $productCategory->timeSeries()->create([
                'frequency' => $frequency,
                'type'      => $productCategory->type->value
            ]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        ProductCategoryTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(ProductCategoryTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $categoryColumn = match ($timeSeries->type) {
            'department'     => 'department_id',
            'sub_department' => 'sub_department_id',
            'family'         => 'family_id',
        };

        $query = DB::table('invoice_transactions')
            ->where($categoryColumn, $timeSeries->product_category_id)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getPortfolioStats($timeSeries->product_category_id, $timeSeries->type, $periodFrom, $periodTo);

            $timeSeries->records()->updateOrCreate(
                [
                    'product_category_time_series_id' => $timeSeries->id,
                    'period'                          => $period,
                    'type'                            => match ($timeSeries->type) {
                        'department'     => 'D',
                        'sub_department' => 'S',
                        'family'         => 'F',
                    },
                    'frequency'                       => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                        => $periodFrom,
                    'to'                          => $periodTo,
                    'sales_external'              => $result->sales_external,
                    'sales_org_currency_external' => $result->sales_org_currency_external,
                    'sales_grp_currency_external' => $result->sales_grp_currency_external,
                    'customers_invoiced'          => $result->customers_invoiced,
                    'invoices'                    => $result->invoices,
                    'refunds'                     => $result->refunds,
                    'orders'                      => $result->orders,
                    'sold'                        => $result->sold,
                    ...$metrics,
                ]
            );

            $processedPeriods[] = $period;
        }

        $this->processPeriodsWithoutInvoices($timeSeries, $from, $to, $processedPeriods);
    }

    protected function processPeriodsWithoutInvoices(ProductCategoryTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $metrics = $this->getPortfolioStats($timeSeries->product_category_id, $timeSeries->type, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $timeSeries->records()->updateOrCreate(
                [
                    'product_category_time_series_id' => $timeSeries->id,
                    'period'                          => $periodData['period'],
                    'type'                            => match ($timeSeries->type) {
                        'department'     => 'D',
                        'sub_department' => 'S',
                        'family'         => 'F',
                    },
                    'frequency'                       => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'                        => $periodData['from'],
                    'to'                          => $periodData['to'],
                    'sales_external'              => 0,
                    'sales_org_currency_external' => 0,
                    'sales_grp_currency_external' => 0,
                    'customers_invoiced'          => 0,
                    'invoices'                    => 0,
                    'refunds'                     => 0,
                    'orders'                      => 0,
                    'sold'                        => 0,
                    ...$metrics,
                ]
            );
        }
    }

    protected function getPortfolioStats(int $productCategoryId, string $type, Carbon $periodFrom, Carbon $periodTo): array
    {
        $categoryColumn = match ($type) {
            'department'     => 'department_id',
            'sub_department' => 'sub_department_id',
            'family'         => 'family_id',
        };

        $assetIds = DB::table('products')
            ->where($categoryColumn, $productCategoryId)
            ->where('is_main', true)
            ->pluck('asset_id');

        if ($assetIds->isEmpty()) {
            return ['dropshippers' => 0, 'listings' => 0];
        }

        $result = DB::table('portfolios')
            ->selectRaw('COUNT(id) as total_listed, COUNT(DISTINCT customer_id) as total_customers')
            ->where('item_type', 'Product')
            ->whereIn('item_id', $assetIds)
            ->where('last_added_at', '>=', $periodFrom)
            ->where('last_added_at', '<=', $periodTo)
            ->whereNull('last_removed_at')
            ->first();

        return [
            'dropshippers' => $result->total_customers ?? 0,
            'listings'     => $result->total_listed ?? 0,
        ];
    }
}
