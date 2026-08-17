<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 11:41:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToHandlingBlocked;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class UpdateDeliveryNoteStateToPicked extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;


    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        $this->trimOverPickedItems($deliveryNote);

        $deliveryNote = DB::transaction(function () use ($deliveryNote) {


            $hasWaiting = $deliveryNote->deliveryNoteItems->where('has_waiting_warehouse', true)->count() || $deliveryNote->deliveryNoteItems->where('has_waiting_crm', true)->count() || $deliveryNote->deliveryNoteItems->where('is_dirty', true)->count();

            if ($hasWaiting) {
                data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value);
                data_set($modelData, 'handling_blocked_at', now());
                if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                    UpdateOrderStateToHandlingBlocked::make()->action($deliveryNote->orders->first(), $deliveryNote);
                }
            } else {
                data_set($modelData, 'state', DeliveryNoteStateEnum::PICKED->value);
                data_set($modelData, 'picked_at', now());
                if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                    UpdateOrderStateToPicked::make()->action($deliveryNote->orders->first(), $deliveryNote);
                }
            }


            return $this->update($deliveryNote, $modelData);
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $deliveryNote->state);

        return $deliveryNote;
    }

    /**
     * Self repair before the note leaves picking: anything picked above the required quantity
     * (HELP-2949 was a double-submit race) is walked back through UpdatePicking so the stock
     * movements are reversed, never dispatched to the customer.
     */
    protected function trimOverPickedItems(DeliveryNote $deliveryNote): void
    {
        foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $excess = (float)$deliveryNoteItem->quantity_picked - (float)$deliveryNoteItem->quantity_required;
            if ($excess <= 0.000001) {
                continue;
            }

            $pickings = $deliveryNoteItem->pickings()
                ->whereIn('type', [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK])
                ->orderByDesc('id')
                ->get();

            foreach ($pickings as $picking) {
                if ($excess <= 0.000001) {
                    break;
                }
                $trim = min($excess, (float)$picking->quantity);
                UpdatePicking::make()->action($picking, ['quantity' => (float)$picking->quantity - $trim]);
                $excess -= $trim;
            }

            /*
             * Same rule as StorePicking: once picked covers required the line is no longer
             * dirty. UpdatePicking does not clear the flag, and without this a line whose
             * required was lowered after picking would block the note in HANDLING_BLOCKED
             * forever (no further pick ever happens, so nothing else clears it).
             */
            $deliveryNoteItem->refresh();
            if ($deliveryNoteItem->is_dirty && (float)$deliveryNoteItem->quantity_picked >= (float)$deliveryNoteItem->quantity_required) {
                $deliveryNoteItem->update(['is_dirty' => false]);
            }
        }

        $deliveryNote->unsetRelation('deliveryNoteItems');
    }

}
