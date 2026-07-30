<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 01:43:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\AssetTimeSeries;

use App\Actions\Catalogue\AssetTimeSeries\Hydrators\AssetTimeSeriesHydrateNumberRecords;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\AssetTimeSeries;
use App\Traits\BuildsInvoiceTransactionTimeSeriesQuery;
use App\Traits\UpsertsTimeSeriesRecords;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAssetTimeSeriesRecords implements ShouldBeUnique
{
    use AsAction;
    use BuildsInvoiceTransactionTimeSeriesQuery;
    use UpsertsTimeSeriesRecords;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $assetId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): string
    {
        return "$assetId:$frequency->value:$from:$to";
    }

    public function handle(int $assetId, TimeSeriesFrequencyEnum $frequency, string $from, string $to): void
    {
        [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

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
        } elseif ($timeSeries->shop_id === null && $asset->shop_id !== null) {
            $timeSeries->update(['shop_id' => $asset->shop_id]);
        }

        $this->processTimeSeries($timeSeries, $from, $to);

        AssetTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    }

    protected function processTimeSeries(AssetTimeSeries $timeSeries, string $from, string $to): void
    {
        $processedPeriods = [];
        $rows             = [];

        $query = DB::connection('aiku_no_sticky')->table('invoice_transactions')
            ->where('asset_id', $timeSeries->asset_id)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNull('deleted_at');

        $results = $this->applyFrequencyGrouping($query, $timeSeries->frequency)->get();

        foreach ($results as $result) {
            ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriod($result, $timeSeries->frequency);

            $metrics = $this->getPortfolioStats($timeSeries->asset_id, $periodFrom, $periodTo);

            $rows[] = [
                'asset_time_series_id' => $timeSeries->id,
                'period'               => $period,
                'frequency'            => $timeSeries->frequency->singleLetter(),
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
                    'sold'                        => $result->sold,
                    ...$metrics,
                ]
            ];

            $processedPeriods[] = $period;
        }

        $rows = [...$rows, ...$this->periodsWithoutInvoicesRows($timeSeries, $from, $to, $processedPeriods)];

        $this->upsertTimeSeriesRecords($timeSeries, $rows, ['asset_time_series_id', 'period', 'frequency']);
    }

    protected function periodsWithoutInvoicesRows(AssetTimeSeries $timeSeries, string $from, string $to, array $processedPeriods): array
    {
        $rows = [];

        $nonInvoicePeriods = TimeSeriesPeriodCalculator::getNonInvoicePeriods($timeSeries->frequency, $from, $to, $processedPeriods);

        foreach ($nonInvoicePeriods as $periodData) {
            $metrics = $this->getPortfolioStats($timeSeries->asset_id, $periodData['from'], $periodData['to']);

            $hasActivity = collect($metrics)->some(fn ($value) => $value != 0 && $value !== null);

            if (!$hasActivity) {
                continue;
            }

            $rows[] = [
                'asset_time_series_id' => $timeSeries->id,
                'period'               => $periodData['period'],
                'frequency'            => $timeSeries->frequency->singleLetter(),
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
                    'sold'                        => 0,
                    ...$metrics,
                ]
            ];
        }

        return $rows;
    }

    protected function getPortfolioStats(int $assetId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $portfolioStats = [
            'dropshippers' => 0,
            'listings'     => 0,
        ];

        $asset = Asset::on('aiku_no_sticky')->find($assetId);
        if ($asset && $asset->shop->type == ShopTypeEnum::DROPSHIPPING) {
            $result = DB::connection('aiku_no_sticky')->table('portfolios')
                ->selectRaw('COUNT(id) as total_listed, COUNT(DISTINCT customer_id) as total_customers')
                ->where('item_type', 'Product')
                ->where('item_id', $asset->product->id)
                ->where('last_added_at', '>=', $periodFrom)
                ->where('last_added_at', '<=', $periodTo)
                ->whereNull('last_removed_at')
                ->first();

            $portfolioStats = [
                'dropshippers' => $result->total_customers ?? 0,
                'listings'     => $result->total_listed ?? 0,
            ];
        }

        return $portfolioStats;
    }
}
