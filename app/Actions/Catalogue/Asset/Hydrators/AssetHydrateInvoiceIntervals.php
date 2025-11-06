<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-09h-48m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateIntervals;
use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateInvoiceIntervals implements ShouldBeUnique
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
        $queryBase = DB::table('invoice_transactions')->whereNull('deleted_at')->where('asset_id', $asset->id)->selectRaw('count(distinct invoice_id) as  sum_aggregate  ');


        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'invoices_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $asset->orderingIntervals()->update($stats);
    }

}
