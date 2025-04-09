<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-10h-57m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateIntervals;
use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateOrders implements ShouldBeUnique
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

        // TODO: #1446 refactor remove the with
        foreach ($dateRanges as $key => $range) {
            if ($key === 'all') {
                $orders = $asset->transactions()
                    ->with('order')
                    ->get()
                    ->pluck('order')
                    ->filter()
                    ->unique('id');
            } else {
                [$start, $end] = $range;

                $orders = $asset->transactions()
                    ->with('order')
                    ->whereHas('order', function ($query) use ($start, $end) {
                        $query->whereBetween('created_at', [$start, $end]);
                    })
                    ->get()
                    ->pluck('order')
                    ->filter()
                    ->unique('id');
            }

            $stats["orders_$key"] = $orders->count();
        }


        $asset->orderingIntervals()->update($stats);

    }

}
