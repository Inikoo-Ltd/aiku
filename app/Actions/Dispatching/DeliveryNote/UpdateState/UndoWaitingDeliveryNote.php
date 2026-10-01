<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\Picking\UndoSetAsWaitingWarehouse;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\Picking;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandling;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UndoWaitingDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    /**
     * Steps a waiting note back to picking by handing its items waiting for the warehouse back to
     * the picker. Items waiting for customer services stay with them: they are released from the
     * waiting page, so a note left with nothing but those to pick is refused rather than sent to a
     * picker with nothing to do. A note waiting on a set sold only complete also gets the parts of
     * that set marked as not picked back, so the picker can look for them again instead of the
     * rest of the set having to go back on the shelf.
     *
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            $deliveryNote = DeliveryNote::whereKey($deliveryNote->id)->lockForUpdate()->firstOrFail();

            if ($deliveryNote->state !== DeliveryNoteStateEnum::HANDLING_BLOCKED) {
                throw ValidationException::withMessages([
                    'message' => __('This delivery note is not waiting any more'),
                ]);
            }

            $this->reopenNotPickedPartsOfIncompleteSets($deliveryNote);

            $deliveryNoteItems = $deliveryNote->deliveryNoteItems()
                ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
                ->lockForUpdate()
                ->get();

            $hasItemsToPick = $deliveryNoteItems->contains(
                fn (DeliveryNoteItem $deliveryNoteItem) => $this->quantityToPickOnceBackInPicking($deliveryNoteItem) > 0
            );

            if (!$hasItemsToPick) {
                throw ValidationException::withMessages([
                    'message' => __('Nothing would be left to pick. Items waiting for customer services have to be sent back to the warehouse from the waiting page first.'),
                ]);
            }

            foreach ($deliveryNoteItems as $deliveryNoteItem) {
                if ((float)$deliveryNoteItem->quantity_waiting_warehouse > 0) {
                    UndoSetAsWaitingWarehouse::run($deliveryNoteItem);
                }
            }

            $deliveryNote = $this->update($deliveryNote, [
                'state'               => DeliveryNoteStateEnum::HANDLING->value,
                'handling_blocked_at' => null,
            ]);

            $order = $deliveryNote->orders->first();
            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT && $order && $order->state == OrderStateEnum::HANDLING_BLOCKED) {
                UpdateOrderStateToHandling::make()->action($order);
            }

            return $deliveryNote;
        });

        foreach ($deliveryNote->pickingSessions as $pickingSession) {
            if (in_array($pickingSession->state, [PickingSessionStateEnum::HANDLING_BLOCKED, PickingSessionStateEnum::PICKING_FINISHED], true)) {
                $pickingSession->update([
                    'state'            => PickingSessionStateEnum::HANDLING,
                    'is_waiting_ready' => true,
                ]);
                WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
            }
        }

        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::HANDLING_BLOCKED);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::HANDLING);

        return $deliveryNote;
    }

    private function reopenNotPickedPartsOfIncompleteSets(DeliveryNote $deliveryNote): void
    {
        $partsBeyondCompleteSets = $deliveryNote->incompleteSetItems()->get(['delivery_note_items.id', 'delivery_note_items.transaction_id']);

        if ($partsBeyondCompleteSets->isEmpty()) {
            return;
        }

        $notPickedParts = $deliveryNote->deliveryNoteItems()
            ->whereIn('transaction_id', $partsBeyondCompleteSets->pluck('transaction_id')->unique())
            ->whereNotIn('id', $partsBeyondCompleteSets->pluck('id'))
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('quantity_not_picked', '>', 0)
            ->lockForUpdate()
            ->get();

        foreach ($notPickedParts as $notPickedPart) {
            $notPickedPart->pickings()
                ->where('type', PickingTypeEnum::NOT_PICK)
                ->get()
                ->each(fn (Picking $notPick) => $notPick->delete());

            CalculateDeliveryNoteItemTotalPicked::make()->action($notPickedPart->refresh());
        }
    }

    private function quantityToPickOnceBackInPicking(DeliveryNoteItem $deliveryNoteItem): float
    {
        return max(0, round(
            (float)$deliveryNoteItem->quantity_required
            - (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_not_picked
            - (float)$deliveryNoteItem->quantity_waiting_crm,
            3
        ));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $deliveryNote = $request->route('deliveryNote');

        return $request->user()->authTo([
            "supervisor-dispatching.$deliveryNote->warehouse_id",
            "org-admin.$deliveryNote->organisation_id",
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
