<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Thu, 02 Jul 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItems;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateUnassigned;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateHandlingResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateUnassignedResource;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

/**
 * Returns a single delivery note item serialized with the exact same resource the items
 * table uses for the delivery note's current state, so the frontend can replace one row
 * in place after an action instead of reloading the whole table via Inertia.
 * Returns data: null when the item no longer matches the requested tab's filter,
 * signalling the frontend to drop the row.
 */
class FetchDeliveryNoteItemRow extends OrgAction
{
    public function handle(DeliveryNoteItem $deliveryNoteItem, ?string $tab = null): ?JsonResource
    {
        $deliveryNote = $deliveryNoteItem->deliveryNote;

        if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED || $deliveryNote->state == DeliveryNoteStateEnum::QUEUED) {
            $items    = IndexDeliveryNoteItemsStateUnassigned::run($deliveryNote, null, deliveryNoteItemId: $deliveryNoteItem->id);
            $resource = DeliveryNoteItemsStateUnassignedResource::class;
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING) {
            $items    = IndexDeliveryNoteItemsStateHandling::run($deliveryNote, null, deliveryNoteItemId: $deliveryNoteItem->id);
            $resource = DeliveryNoteItemsStateHandlingResource::class;
        } else {
            $stateFilter = match ($tab) {
                'pending_items' => DeliveryNoteItemStateEnum::PACKING,
                'done_items'    => DeliveryNoteItemStateEnum::PACKED,
                default         => null,
            };

            $items    = IndexDeliveryNoteItems::run($deliveryNote, null, $stateFilter, deliveryNoteItemId: $deliveryNoteItem->id);
            $resource = DeliveryNoteItemsResource::class;
        }

        $item = collect($items->items())->first();

        return $item ? new $resource($item) : null;
    }

    public function jsonResponse(?JsonResource $deliveryNoteItemRow): array
    {
        return [
            'data' => $deliveryNoteItemRow,
        ];
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): ?JsonResource
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $request->input('tab'));
    }
}
