<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 23:46:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\CollectionTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PreprocessCollectionTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $assetID): string
    {
        return $assetID ?? 'empty';
    }

    public function handle(int|null $assetID): void
    {
        if (!$assetID) {
            return;
        }
        $asset = Asset::find($assetID);
        if (!$asset || !$asset->product) {
            return;
        }

        $collectionsIds = DB::table('collection_has_models')
            ->where('model_type', 'Product')
            ->where('model_id', $asset->product->id)
            ->pluck('collection_id')->unique()->toArray();


        foreach ($collectionsIds as $collectionsId) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessCollectionTimeSeriesRecords::dispatch(
                    $collectionsId,
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
