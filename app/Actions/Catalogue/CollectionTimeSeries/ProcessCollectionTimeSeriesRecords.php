<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 23:02:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\CollectionTimeSeries;

use App\Actions\Catalogue\CollectionTimeSeries\Hydrators\CollectionTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\CollectionTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use App\Traits\UpsertsTimeSeriesRecords;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessCollectionTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;
    use UpsertsTimeSeriesRecords;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $collectionId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$collectionId:$frequency->value:$from:$to";
    }

    public function handle(int $collectionId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

        $collection = Collection::find($collectionId);

        if (!$collection) {
            return;
        }

        $timeSeries = CollectionTimeSeries::where('collection_id', $collection->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $collection->timeSeries()->create(['frequency' => $frequency]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        CollectionTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(CollectionTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];
        $rows             = [];

        $assetsIDs = $timeSeries->collection->products->pluck('id')->unique()->toArray();

        $query = DB::connection('aiku_no_sticky')->table('invoice_transactions')
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->whereIn('asset_id', $assetsIDs)
            ->where('invoices.date', '>=', $from)
            ->where('invoices.date', '<=', $to)
            ->whereNull('invoice_transactions.deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $rows[] = [
                'collection_time_series_id' => $timeSeries->id,
                'period'                    => $period,
                'frequency'                 => $timeSeries->frequency->singleLetter(),
                ...[
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
            ];

            $processedPeriods[] = $period;
        }

        $rows = [...$rows, ...$this->periodsWithoutInvoicesRows($timeSeries, $from, $to, $processedPeriods)];

        $this->upsertTimeSeriesRecords($timeSeries, $rows, ['collection_time_series_id', 'period', 'frequency']);
    }

    protected function periodsWithoutInvoicesRows(CollectionTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): array
    {
        $rows = [];

        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $rows[] = [
                'collection_time_series_id' => $timeSeries->id,
                'period'                    => $periodData['period'],
                'frequency'                 => $timeSeries->frequency->singleLetter(),
                ...[
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
            ];
        }

        return $rows;
    }
}
