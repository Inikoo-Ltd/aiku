<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-11h-13m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateDeliveryNotesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $assetID, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($assetID, $intervals, $doPreviousPeriods);
    }

    public function handle(int $assetID, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {

        $asset = Asset::find($assetID);
        if (!$asset) {
            return;
        }

        $stats     = [];
        $queryBase = DB::table('delivery_note_items')
            ->join('transactions', 'transaction_id', '=', 'transactions.id')
            ->where('transactions.asset_id', $asset->id)->selectRaw('count(distinct delivery_note_id) as  sum_aggregate');


        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'delivery_notes_',
            dateField: 'delivery_note_items.date',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $asset->orderingIntervals()->update($stats);



    }

}
