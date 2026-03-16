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

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int|null $assetID, ?string $date = null): string
    {
        return ($assetID ?? 'empty').':'.($date ?? now()->toDateString());
    }

    public function handle(int|null $assetID, ?string $date = null): void
    {
        if (!$assetID) {
            return;
        }

        $asset = Asset::find($assetID);

        if (!$asset || !$asset->product) {
            return;
        }

        $resolvedDate = $date ?? now()->toDateString();
        $carbon       = \Carbon\Carbon::parse($resolvedDate);

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
