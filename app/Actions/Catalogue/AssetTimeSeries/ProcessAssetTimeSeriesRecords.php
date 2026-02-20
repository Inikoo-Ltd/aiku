<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 01:43:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\AssetTimeSeries;

use App\Actions\Catalogue\AssetTimeSeries\Hydrators\AssetTimeSeriesHydrateNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\AssetTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAssetTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;

    public function getJobUniqueId(int $assetId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$assetId:$frequency->value:$from:$to";
    }

    public function handle(int $assetId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        $from .= ' 00:00:00';
        $to   .= ' 23:59:59';

        $asset = Asset::find($assetId);

        if (!$asset) {
            return;
        }

        $timeSeries = AssetTimeSeries::where('asset_id', $asset->id)->where('frequency', $frequency->value)->first();

        if (!$timeSeries) {
            $timeSeries = $asset->timeSeries()->create([
                'frequency' => $frequency,
                'shop_id'   => $asset->shop_id
            ]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        AssetTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(AssetTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];

        $query = DB::table('invoice_transactions')
            ->where('asset_id', $timeSeries->asset_id)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $timeSeries->records()->updateOrCreate(
                [
                    'asset_time_series_id' => $timeSeries->id,
                    'period'               => $period,
                    'frequency'            => $timeSeries->frequency->singleLetter()
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

    protected function processPeriodsWithoutInvoices(AssetTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): void
    {
        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $timeSeries->records()->updateOrCreate(
                [
                    'asset_time_series_id' => $timeSeries->id,
                    'period'               => $periodData['period'],
                    'frequency'            => $timeSeries->frequency->singleLetter()
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
