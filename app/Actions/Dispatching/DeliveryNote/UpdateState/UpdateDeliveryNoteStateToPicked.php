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
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class UpdateDeliveryNoteStateToPicked extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;


    /**
     * Trimming edits pickings, and editing a picking re-evaluates the note, which can land back
     * here while the state on the row still reads as it did on entry. This stops the re-entry
     * inside one call stack; the row lock below is what stops two workers doing it at once.
     */
    private static array $inProgress = [];

    /**
     * The one answer to "is something on this note still holding it". Asking it in two places with
     * two slightly different sets of conditions is what produced notes that could be released and
     * then immediately re-blocked, so the release path reads it from here too.
     *
     * A dirty line holding MORE than is now wanted is deliberately not blocking: the trim below is
     * what settles that one, and calling it blocking would refuse to start the very repair.
     *
     * Note this asks nothing about is_handled. A note whose picking is finished with a line nobody
     * could pick is picked-and-short, not blocked, and marking it blocked would strand it. Only
     * the spontaneous release in AutoFinishWaitingDeliveryNote cares about unfinished work, and it
     * asks that question separately.
     */
    public static function isBlocked(DeliveryNote $deliveryNote): bool
    {
        return $deliveryNote->deliveryNoteItems()
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where(function ($query) {
                $query->where('has_waiting_warehouse', true)
                    ->orWhere('has_waiting_crm', true)
                    ->orWhere(function ($query) {
                        $query->where('is_dirty', true)
                            ->whereColumn('quantity_picked', '<=', 'quantity_required');
                    });
            })
            ->exists();
    }

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        if (isset(self::$inProgress[$deliveryNote->id])) {
            return $deliveryNote;
        }

        self::$inProgress[$deliveryNote->id] = true;

        try {
            return $this->transitionToPicked($deliveryNote);
        } finally {
            unset(self::$inProgress[$deliveryNote->id]);
        }
    }

    /**
     * @throws \Throwable
     */
    private function transitionToPicked(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        $this->trimOverPickedItems($deliveryNote);

        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            /*
             * Two workers can reach this for the same note - a picker scanning while the
             * marketplace polls. Whoever gets the lock second finds the work already done and
             * leaves it alone, rather than stamping picked_at again, repricing every transaction
             * on the order again, and handing the hydrators an old state that has already been
             * counted away.
             */
            $currentState = DeliveryNote::whereKey($deliveryNote->id)->lockForUpdate()->value('state');

            if ($currentState == DeliveryNoteStateEnum::PICKED->value) {
                return $deliveryNote->refresh();
            }

            if (self::isBlocked($deliveryNote)) {
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
        }

        $deliveryNote->unsetRelation('deliveryNoteItems');
    }

}
