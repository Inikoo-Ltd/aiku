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
use Illuminate\Support\Facades\DB;
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
        if ($asset->model_type != 'Product') {
            return;
        }

        $dateRanges = $this->getDateRanges();
        $stats      = [];

        foreach ($dateRanges as $key => $range) {
            if ($key === 'all') {
                $deliveryNotesData = DB::table('delivery_note_items')
                    ->leftJoin('product_has_org_stocks', 'product_has_org_stocks.org_stock_id', '=', 'delivery_note_items.org_stock_id')
                    ->select(DB::raw('COUNT(DISTINCT delivery_note_items.delivery_note_id) as count'))
                    ->where('product_has_org_stocks.product_id', $asset->model_id)
                    ->first();
            } else {
                [$start, $end] = $range;

                $deliveryNotesData = DB::table('delivery_note_items')
                    ->leftJoin('product_has_org_stocks', 'product_has_org_stocks.org_stock_id', '=', 'delivery_note_items.org_stock_id')
                    ->leftJoin('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->select(DB::raw('COUNT(DISTINCT delivery_notes.id) as count'))
                    ->whereBetween('delivery_notes.created_at', [$start, $end])
                    ->where('product_has_org_stocks.product_id', $asset->model_id)
                    ->first();
            }

            $stats["delivery_notes_$key"] = $deliveryNotesData->count;
        }

        $asset->orderingIntervals()->update($stats);
    }

}
