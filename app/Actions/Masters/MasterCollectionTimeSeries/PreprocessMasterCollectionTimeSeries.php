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

    public function getJobUniqueId(int|null $masterAssetID, ?string $date = null): string
    {
        return ($masterAssetID ?? 'empty').':'.($date ?? now()->toDateString());
    }

    public function handle(int|null $masterAssetID, ?string $date = null): void
    {
        if (!$masterAssetID) {
            return;
        }

        $resolvedDate = $date ?? now()->toDateString();
        $carbon       = \Carbon\Carbon::parse($resolvedDate);

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
                        TimeSeriesFrequencyEnum::YEARLY    => $carbon->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $carbon->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY   => $carbon->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY    => $carbon->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY     => $resolvedDate,
                    },
                    $resolvedDate
                )->delay(1800);
            }
        }
    }
}
