<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Fri, 03 Jul 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\PickingSession\Json;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSession;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionGrouped;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionStateActive;
use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsGroupedResource;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsStateHandlingResource;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsStateUnassignedResource;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

/**
 * Returns a single row of the picking session items table serialized with the exact same
 * resource as the requested tab, so the frontend can replace one row in place after an
 * action instead of reloading the whole table via Inertia. For the 'grouped' tab rowId is
 * a delivery note id (one row per delivery note); for the other tabs it is a delivery note
 * item id. Returns data: null when the row no longer exists in the tab's dataset.
 */
class FetchPickingSessionItemRow extends OrgAction
{
    public function handle(PickingSession $pickingSession, ?string $tab, int $rowId): ?JsonResource
    {
        if ($tab == 'grouped') {
            $items    = IndexDeliveryNoteItemsInPickingSessionGrouped::run($pickingSession, null, deliveryNoteId: $rowId);
            $resource = PickingSessionDeliveryNoteItemsGroupedResource::class;
        } elseif ($tab == 'items') {
            $items    = IndexDeliveryNoteItemsInPickingSession::run($pickingSession, null, deliveryNoteItemId: $rowId);
            $resource = PickingSessionDeliveryNoteItemsStateUnassignedResource::class;
        } else {
            $items    = IndexDeliveryNoteItemsInPickingSessionStateActive::run($pickingSession, null, deliveryNoteItemId: $rowId);
            $resource = PickingSessionDeliveryNoteItemsStateHandlingResource::class;
        }

        $item = collect($items->items())->first();

        return $item ? new $resource($item) : null;
    }

    public function jsonResponse(?JsonResource $pickingSessionItemRow): array
    {
        return [
            'data' => $pickingSessionItemRow,
        ];
    }

    public function rules(): array
    {
        return [
            'tab'    => ['nullable', 'string'],
            'row_id' => ['required', 'integer'],
        ];
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): ?JsonResource
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData['tab'] ?? null, (int) $this->validatedData['row_id']);
    }
}
