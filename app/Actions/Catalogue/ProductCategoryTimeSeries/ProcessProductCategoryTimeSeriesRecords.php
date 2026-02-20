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
            'department' => 'department_id',
            'sub_department' => 'sub_department_id',
            'family' => 'family_id',
        };

        $query = DB::table('invoice_transactions')
            ->where($categoryColumn, $timeSeries->product_category_id)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

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
                ]
            );
        }
    }
}
