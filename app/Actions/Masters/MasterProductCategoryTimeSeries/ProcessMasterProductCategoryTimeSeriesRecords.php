<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:53:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Actions\Masters\MasterProductCategoryTimeSeries\Hydrators\MasterProductCategoryTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryTimeSeries;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMasterProductCategoryTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $masterProductCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$masterProductCategoryId:$frequency->value:$from:$to";
    }

    public function handle(int $masterProductCategoryId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $masterProductCategory = MasterProductCategory::find($masterProductCategoryId);

        if (!$masterProductCategory) {
            return;
        }

        $timeSeries = MasterProductCategoryTimeSeries::where('master_product_category_id', $masterProductCategoryId)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $masterProductCategory->timeSeries()->create([
                'frequency' => $frequency,
                'type'      => $masterProductCategory->type->value
            ]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        MasterProductCategoryTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(MasterProductCategoryTimeSeries $timeSeries, string $from, string $to): void
    {
        $categoryColumn = match ($timeSeries->type) {
            'department' => 'master_department_id',
            'sub_department' => 'master_sub_department_id',
            'family' => 'master_family_id',
        };

        $results = DB::table('invoice_transactions')
            ->where($categoryColumn, $timeSeries->master_product_category_id)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

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
                    'master_product_category_time_series_id' => $timeSeries->id,
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
