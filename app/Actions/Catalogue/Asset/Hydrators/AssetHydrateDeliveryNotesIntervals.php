<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-11h-13m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateIntervals;
use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateDeliveryNotesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateIntervals;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }

    public function handle(Asset $asset): void
    {

        $dateRanges = $this->getDateRanges();
        $stats = [];

        foreach ($dateRanges as $key => $range) {
            if ($key === 'all') {
                $deliveryNotes = $asset->transactions()
                    ->with('deliveryNoteItem.deliveryNote')
                    ->get()
                    ->pluck('deliveryNoteItem')
                    ->pluck('deliveryNote')
                    ->filter()
                    ->unique('id');
            } else {
                [$start, $end] = $range;

                $deliveryNotes = $asset->transactions()
                    ->with('deliveryNoteItem.deliveryNote')
                    ->whereHas('deliveryNoteItem.deliveryNote', function ($query) use ($start, $end) {
                        $query->whereBetween('created_at', [$start, $end]);
                    })
                    ->get()
                    ->pluck('deliveryNoteItem')
                    ->pluck('deliveryNote')
                    ->filter()
                    ->unique('id');
            }

            $stats["delivery_notes_$key"] = $deliveryNotes->count();
        }

        $asset->orderingIntervals()->update($stats);

    }

}
