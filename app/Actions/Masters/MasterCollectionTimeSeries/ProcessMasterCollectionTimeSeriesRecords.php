<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 03:15:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries;

use App\Actions\Masters\MasterCollectionTimeSeries\Hydrators\MasterCollectionTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterCollectionTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMasterCollectionTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public function getJobUniqueId(int $masterCollectionId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$masterCollectionId:$frequency->value:$from:$to";
    }

    public function handle(int $masterCollectionId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $masterCollection = MasterCollection::find($masterCollectionId);

        if (!$masterCollection) {
            return;
        }

        $timeSeries = MasterCollectionTimeSeries::where('master_collection_id', $masterCollection->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $masterCollection->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        MasterCollectionTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(MasterCollectionTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $masterAssetsIDs = $timeSeries->masterCollection->masterProducts->pluck('id')->unique()->toArray();

        $query = DB::table('invoice_transactions')
            ->whereIn('master_asset_id', $masterAssetsIDs)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'master_collection_time_series_id' => $timeSeries->id,
                    'period'                           => $period,
                    'frequency'                        => $timeSeries->frequency->singleLetter()
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

    protected function processPeriodsWithoutInvoices(MasterCollectionTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'master_collection_time_series_id' => $timeSeries->id,
                    'period'                           => $periodData['period'],
                    'frequency'                        => $timeSeries->frequency->singleLetter()
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
