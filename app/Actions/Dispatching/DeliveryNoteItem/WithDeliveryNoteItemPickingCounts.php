<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-08-12 15:50
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;

/**
 * The counts behind the picking tabs, read from the same two conditions the tab queries use, so the
 * badge a picker reads and the rows they land on can never disagree. A scan answers with the refreshed
 * counts, which is why this has to be shared with the scan endpoint and not inlined in the page.
 *
 * @phpstan-type PickingCounts array{all: int, todo: int, done: int}
 */
trait WithDeliveryNoteItemPickingCounts
{
    /**
     * @return array{all: int, todo: int, done: int}
     */
    public static function pickingCounts(DeliveryNote $deliveryNote): array
    {
        $all = DeliveryNoteItem::where('delivery_note_id', $deliveryNote->id)
            ->where(function ($query) {
                $query->where('quantity_required', '>', 0)
                    ->orWhere('is_dirty', true);
            });

        $done = (clone $all)
            ->where('is_handled', true)
            ->where('is_dirty', false)
            ->count();

        $total = $all->count();

        return [
            'all'  => $total,
            'todo' => $total - $done,
            'done' => $done,
        ];
    }
}
