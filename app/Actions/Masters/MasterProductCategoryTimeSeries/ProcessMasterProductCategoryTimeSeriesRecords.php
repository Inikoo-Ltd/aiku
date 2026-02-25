<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:53:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Actions\Masters\MasterProductCategoryTimeSeries\Hydrators\MasterProductCategoryTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMasterProductCategoryTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public string $jobQueue = 'sales';

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
        $processedPeriods = [];

        $categoryColumn = match ($timeSeries->type) {
            'department' => 'master_department_id',
            'sub_department' => 'master_sub_department_id',
            'family' => 'master_family_id',
        };

        $query = DB::table('invoice_transactions')
            ->where($categoryColumn, $timeSeries->master_product_category_id)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'master_product_category_time_series_id' => $timeSeries->id,
                    'period'                                 => $period,
                    'type'                                   => match ($timeSeries->type) {
                        'department'     => 'D',
                        'sub_department' => 'S',
                        'family'         => 'F',
                    },
                    'frequency'                              => $timeSeries->frequency->singleLetter()
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

    protected function processPeriodsWithoutInvoices(MasterProductCategoryTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'master_product_category_time_series_id' => $timeSeries->id,
                    'period'                                 => $periodData['period'],
                    'type'                                   => match ($timeSeries->type) {
                        'department'     => 'D',
                        'sub_department' => 'S',
                        'family'         => 'F',
                    },
                    'frequency'                              => $timeSeries->frequency->singleLetter()
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
