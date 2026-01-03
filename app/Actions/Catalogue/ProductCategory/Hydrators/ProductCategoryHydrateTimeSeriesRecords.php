<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithTimeSeriesRecordsGeneration;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\ProductCategoryTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public function getJobUniqueId(int $productCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "hydrate-product-category-time-series-records:$productCategoryId:$frequency->value:$from:$to";
    }

    public function handle(int $productCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {

        $from .= ' 00:00:00';
        $to .= ' 23:59:59';

        $productCategory = ProductCategory::find($productCategoryId);

        $timeSeries = ProductCategoryTimeSeries::where('product_category_id', $productCategoryId)
            ->where('frequency', $frequency->value)->first();
        if (!$timeSeries) {
            $timeSeries = $productCategory->timeSeries()->create([
                'frequency' => $frequency,
                'type'      => $productCategory->type->value
            ]);
        }


        switch ($frequency) {
            case TimeSeriesFrequencyEnum::YEARLY:
            case TimeSeriesFrequencyEnum::QUARTERLY:
            case TimeSeriesFrequencyEnum::MONTHLY:
            case TimeSeriesFrequencyEnum::WEEKLY:
            case TimeSeriesFrequencyEnum::DAILY:
                $this->processYearlyTimeSeries($timeSeries, $from, $to);
                break;
        }


        ProductCategoryHydrateTimeSeriesNumberRecords::run($timeSeries->id);
    }

    protected function processYearlyTimeSeries(ProductCategoryTimeSeries $timeSeries, string $from, string $to): void
    {
        $categoryColumn = match ($timeSeries->type) {
            'department' => 'department_id',
            'sub_department' => 'sub_department_id',
            'family' => 'family_id',
        };

        $results = DB::table('invoice_transactions')
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->where($categoryColumn, $timeSeries->product_category_id);

        if ($timeSeries->frequency == TimeSeriesFrequencyEnum::YEARLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('EXTRACT(QUARTER FROM date) as quarter'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(QUARTER FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('EXTRACT(MONTH FROM date) as month'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(MONTH FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
            $results->select(
                DB::raw('EXTRACT(YEAR FROM date) as year'),
                DB::raw('EXTRACT(WEEK FROM date) as week'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(WEEK FROM date)'));
        } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
            $results->select(
                DB::raw('CAST(date AS DATE) as date'),
                DB::raw('SUM(net_amount) as sales'),
                DB::raw('SUM(org_net_amount) as sales_org_currency'),
                DB::raw('SUM(grp_net_amount) as sales_grp_currency'),
                DB::raw('COUNT(DISTINCT customer_id) as customers_invoiced'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = false THEN invoice_id END) as invoices'),
                DB::raw('COUNT(DISTINCT CASE WHEN is_refund = true THEN invoice_id END) as refunds'),
                DB::raw('COUNT(DISTINCT order_id) as orders'),
            )->groupBy(DB::raw('CAST(date AS DATE)'));
        }


        $results = $results->get();

        foreach ($results as $result) {
            if ($timeSeries->frequency == TimeSeriesFrequencyEnum::QUARTERLY) {
                $periodFrom = Carbon::create((int)$result->year, ((int)$result->quarter - 1) * 3 + 1)->startOfQuarter();
                $periodTo = Carbon::create((int)$result->year, ((int)$result->quarter - 1) * 3 + 1)->endOfQuarter();
                $period     = $result->year.' Q'.$result->quarter;
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::MONTHLY) {
                $periodFrom = Carbon::create((int) $result->year, (int) $result->month)->startOfMonth();
                $periodTo   = Carbon::create((int) $result->year, (int) $result->month)->endOfMonth();
                $period     = $result->year.'-'.str_pad($result->month, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::WEEKLY) {
                $periodFrom = Carbon::create((int) $result->year)->week((int) $result->week)->startOfWeek();
                $periodTo   = Carbon::create((int) $result->year)->week((int) $result->week)->endOfWeek();
                $period     = $result->year.' W'.str_pad($result->week, 2, '0', STR_PAD_LEFT);
            } elseif ($timeSeries->frequency == TimeSeriesFrequencyEnum::DAILY) {
                $periodFrom = Carbon::parse($result->date)->startOfDay();
                $periodTo   = Carbon::parse($result->date)->endOfDay();
                $period     = Carbon::parse($result->date)->format('Y-m-d');
            } else {
                $periodFrom = Carbon::parse((int) $result->year.'-01-01');
                $periodTo   = Carbon::parse((int) $result->year.'-12-31');
                $period     = $result->year;
            }


            $timeSeries->records()->updateOrCreate(
                [
                    'product_category_time_series_id' => $timeSeries->id,
                    'period'                          => $period,
                    'type'                            => match ($timeSeries->type) {
                        'department' => 'D',
                        'sub_department' => 'S',
                        'family' => 'F',
                    },
                    'frequency'                       => $timeSeries->frequency->singleLetter()
                ],
                [
                    'from'               => $periodFrom,
                    'to'                 => $periodTo,
                    'sales'              => $result->sales,
                    'sales_org_currency' => $result->sales_org_currency,
                    'sales_grp_currency' => $result->sales_grp_currency,
                    'customers_invoiced' => $result->customers_invoiced,
                    'invoices'           => $result->invoices,
                    'refunds'            => $result->refunds,
                    'orders'             => $result->orders,

                ]
            );
        }
    }


}
