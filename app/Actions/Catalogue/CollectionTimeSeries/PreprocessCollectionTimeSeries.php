<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 23:46:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\CollectionTimeSeries;

use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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

        $product = $asset->product;
        foreach ($product->collections as $collection) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessAssetTimeSeriesRecords::dispatch(
                    $collection->id,
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
