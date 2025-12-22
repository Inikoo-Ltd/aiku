<?php

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use DB;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';
    public string $commandSignature = 'hydrate:master-collection-sales-intervals {masterShop}';

    public function getJobUniqueId(int $masterCollectionId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($masterCollectionId, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('masterShop'))->first();

        if (!$masterShop) {
            return;
        }

        $masterCollections = MasterCollection::where('master_shop_id', $masterShop->id)->get();

        if (empty($masterCollections)) {
            return;
        }

        foreach ($masterCollections as $masterCollection) {
            $this->handle($masterCollection->id);
        }
    }

    public function handle(int $masterCollectionId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $masterCollection = MasterCollection::find($masterCollectionId);

        if (!$masterCollection) {
            return;
        }

        $stats = [];

        $productIds = DB::table('master_collection_has_models')
            ->where('master_collection_id', $masterCollection->id)
            ->where('model_type', 'MasterAsset')
            ->pluck('model_id');

        $assetIds = DB::table('products')
            ->whereIn('master_product_id', $productIds)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        if ($assetIds->isEmpty()) {
            $masterCollection->salesIntervals()->updateOrCreate(
                ['master_collection_id' => $masterCollection->id],
                []
            );
            return;
        }

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

        $masterCollection->salesIntervals()->updateOrCreate(
            ['master_collection_id' => $masterCollection->id],
            $stats
        );
    }
}
