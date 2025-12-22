<?php

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use DB;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionHydrateOrderingIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';
    public string $commandSignature = 'hydrate:master-collection-ordering-intervals {masterShop}';

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
            $masterCollection->orderingIntervals()->updateOrCreate(
                ['master_collection_id' => $masterCollection->id],
                []
            );
            return;
        }

        $invoiceIdsQuery = DB::table('invoice_transactions')
            ->whereIn('asset_id', $assetIds)
            ->select('invoice_id')
            ->distinct();

        $queryBase = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->selectRaw('count(*) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'invoices_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Invoice::query()
            ->whereIn('id', $invoiceIdsQuery)
            ->where('in_process', false)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->selectRaw('count(*) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'refunds_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $masterCollection->orderingIntervals()->updateOrCreate(
            ['master_collection_id' => $masterCollection->id],
            $stats
        );
    }
}
