<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 15:55:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;

class AutoFinishWaitingDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    /**
     * A note is blocked by a line waiting on the warehouse, a line waiting on CRM, or a line gone
     * dirty because its quantity changed under the picker. This asks about all of them, because
     * whatever put the block on can also be taken away on its own - a dirty line re-picked, a line
     * deleted outright by the marketplace - and a note released on the waiting flags alone then
     * sits blocked with nothing left to clear and no pack button, which is how ggv9wen43n and
     * mxdpk8yece each stranded a warehouse for days. It will not release a note holding a line
     * nobody has finished, which would ship the order short.
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {

        if ($deliveryNote->state != DeliveryNoteStateEnum::HANDLING_BLOCKED) {
            return $deliveryNote;
        }

        /*
         * A note with nothing left on it is not a finished note, it is an empty one. Releasing it
         * would carry the order to picked with no line to pick, which is what a marketplace taking
         * away the last line would otherwise do.
         */
        if (!$deliveryNote->deliveryNoteItems()->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)->exists()) {
            return $deliveryNote;
        }

        if (UpdateDeliveryNoteStateToPicked::isBlocked($deliveryNote)) {
            return $deliveryNote;
        }

        /*
         * Nobody asked for this release, so it must not be the thing that decides an unpicked line
         * is never going to be picked. A human finishing the note can ship it short on purpose;
         * this cannot.
         */
        $hasUnfinishedItems = $deliveryNote->deliveryNoteItems()
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('is_handled', false)
            ->exists();

        if ($hasUnfinishedItems) {
            return $deliveryNote;
        }

        /*
         * This is an opportunistic re-check, not a command, and the order transition it leads to
         * throws on an order that has been cancelled, dispatched or is not submitted yet. Leaving
         * such a note alone is right; blowing up the pick that happened to trigger the check is not.
         */
        $order = $deliveryNote->orders->first();
        if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT && (!$order || in_array($order->state, [
            OrderStateEnum::CANCELLED,
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::CREATING,
            OrderStateEnum::SUBMITTED,
        ]))) {
            return $deliveryNote;
        }

        UpdateDeliveryNoteStateToPicked::run($deliveryNote);

        return $deliveryNote;
    }

    public function getCommandSignature(): string
    {
        return 'dispatching:delivery-note:auto-finish-waiting {delivery_note}';
    }

    public function asController(DeliveryNote $deliveryNote, \Lorisleiva\Actions\ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        return $this->handle($deliveryNote);
    }

    public function asCommand(Command $command): int
    {

        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $this->handle($deliveryNote);
        return 0;
    }



}
