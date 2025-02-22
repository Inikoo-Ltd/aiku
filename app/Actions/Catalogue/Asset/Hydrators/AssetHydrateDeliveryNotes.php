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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateDeliveryNotes
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateIntervals;

    private Asset $asset;

    public function __construct(Asset $asset)
    {
        $this->asset = $asset;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->asset->id))->dontRelease()];
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

            $stats["delivery_notes_{$key}"] = $deliveryNotes->count();
        }

        $asset->orderingIntervals()->update($stats);

    }

}
