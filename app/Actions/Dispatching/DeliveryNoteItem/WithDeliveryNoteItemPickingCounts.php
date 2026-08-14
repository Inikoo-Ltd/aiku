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
 * The counts behind the todo/done tabs, picking and packing alike, each read from the same conditions
 * its tab query uses, so the badge somebody reads and the rows they land on can never disagree. A scan
 * answers with the refreshed picking counts, which is why this is shared rather than inlined the page.
 *
 * @phpstan-type TabCounts array{all: int, todo: int, done: int}
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

    /**
     * An item can be packed over several goes, so what counts as still to pack is the sum of its
     * packings against what was picked, exactly as IndexDeliveryNoteItems splits the two tabs. An
     * item that was never picked has nothing to pack and belongs on the done side.
     *
     * @return array{all: int, todo: int, done: int}
     */
    public static function packingCounts(DeliveryNote $deliveryNote): array
    {
        $packedSoFar = 'round(coalesce((select sum(quantity) from packings where packings.delivery_note_item_id = delivery_note_items.id), 0), 3)';
        $pickedTotal = 'round(delivery_note_items.quantity_picked, 3)';

        $all = DeliveryNoteItem::where('delivery_note_id', $deliveryNote->id)
            ->where(function ($query) {
                $query->where('quantity_required', '>', 0)
                    ->orWhere('is_dirty', true);
            });

        $todo = (clone $all)
            ->whereRaw("$packedSoFar < $pickedTotal")
            ->where('quantity_picked', '!=', 0)
            ->count();

        $total = $all->count();

        return [
            'all'  => $total,
            'todo' => $todo,
            'done' => $total - $todo,
        ];
    }
}
