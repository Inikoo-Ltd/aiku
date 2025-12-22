<?php

namespace App\Actions\Catalogue\Collection\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use DB;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CollectionHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';
    public string $commandSignature = 'hydrate:collection-sales-intervals {shop}';

    public function getJobUniqueId(int $collectionId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($collectionId, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if (!$shop) {
            return;
        }

        $collections = Collection::where('shop_id', $shop->id)->get();

        if (empty($collections)) {
            return;
        }

        foreach ($collections as $collection) {
            $this->handle($collection->id);
        }
    }

    public function handle(int $collectionId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $collection = Collection::find($collectionId);

        if (!$collection) {
            return;
        }

        $stats = [];

        // Get all product IDs in this collection
        $productIds = DB::table('collection_has_models')
            ->where('collection_id', $collection->id)
            ->where('model_type', 'Product')
            ->pluck('model_id');

        // Get all asset IDs from those products
        $assetIds = DB::table('products')
            ->whereIn('id', $productIds)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        if ($assetIds->isEmpty()) {
            $collection->salesIntervals()->update([]);
            return;
        }

        // Get all invoice IDs that contain these assets
        // To filter by specific shop, add: ->where('shop_id', $shopId)
        $invoiceIdsQuery = DB::table('invoice_transactions')
            ->whereIn('asset_id', $assetIds)
            ->select('invoice_id')
            ->distinct();

        $queryBaseSales = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->selectRaw('sum(net_amount) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseSales,
            statField: 'sales_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseSalesGrpCurrency = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->selectRaw('sum(grp_net_amount) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseSalesGrpCurrency,
            statField: 'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseSalesOrgCurrency = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->selectRaw('sum(org_net_amount) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseSalesOrgCurrency,
            statField: 'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $collection->salesIntervals()->update($stats);
    }
}
