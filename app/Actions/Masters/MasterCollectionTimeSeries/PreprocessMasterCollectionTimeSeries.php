<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 03:24:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PreprocessMasterCollectionTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int|null $masterAssetID): string
    {
        return $masterAssetID ?? 'empty';
    }

    public function handle(int|null $masterAssetID): void
    {
        if (!$masterAssetID) {
            return;
        }


        $masterCollectionsIds = DB::table('master_collection_has_models')
            ->where('model_type', 'MasterAsset')
            ->where('model_id', $masterAssetID)
            ->pluck('master_collection_id')->unique()->toArray();


        foreach ($masterCollectionsIds as $masterCollectionsId) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterCollectionTimeSeriesRecords::dispatch(
                    $masterCollectionsId,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }
    }


}
