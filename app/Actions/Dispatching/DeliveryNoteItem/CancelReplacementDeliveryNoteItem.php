<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 16:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use App\Notifications\Work\WorkNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CancelReplacementDeliveryNoteItem extends OrgAction
{
    /**
     * HELP-4265. The customer no longer wants this line of a replacement. It is cancelled the way
     * CancelDeliveryNote cancels each of its lines, picks taken back and the whole requirement set
     * as not picked, but the rest of the note carries on, instead of cancelling the note and
     * picking everything again in a new one.
     *
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, ?User $user): DeliveryNoteItem
    {
        DB::transaction(function () use ($deliveryNoteItem, $user) {
            $deliveryNoteItem = DeliveryNoteItem::whereKey($deliveryNoteItem->id)->lockForUpdate()->firstOrFail();
            $deliveryNote     = $deliveryNoteItem->deliveryNote;

            if ((float)$deliveryNoteItem->quantity_waiting_warehouse > 0 || (float)$deliveryNoteItem->quantity_waiting_crm > 0) {
                throw ValidationException::withMessages([
                    'message' => __('This item is waiting, resolve the waiting first.'),
                ]);
            }

            if ((float)$deliveryNoteItem->quantity_not_picked >= (float)$deliveryNoteItem->quantity_required) {
                throw ValidationException::withMessages([
                    'message' => __('This item is already cancelled.'),
                ]);
            }

            if (!$this->hasOtherLiveItems($deliveryNoteItem)) {
                throw ValidationException::withMessages([
                    'message' => __('This is the last item of the replacement, cancel the delivery note instead.'),
                ]);
            }

            foreach ($deliveryNoteItem->pickings()->where('type', '!=', PickingTypeEnum::NOT_PICK)->get() as $picking) {
                DeletePicking::make()->action($picking, $user);
            }

            $deliveryNoteItem->refresh()->update(['locked_at' => null]);

            $notPicked = StoreNotPickPicking::make()->action(
                $deliveryNoteItem,
                $user,
                [
                    'not_picked_reason' => PickingNotPickedReasonEnum::CANCELLED_BY_CUSTOMER,
                    'not_picked_note'   => "Cancelled from replacement $deliveryNote->reference, anything picked goes back to its location.",
                    'quantity'          => (float)$deliveryNoteItem->quantity_required - (float)$deliveryNoteItem->quantity_not_picked,
                ]
            );

            if (!$notPicked) {
                throw ValidationException::withMessages([
                    'message' => __('The item could not be cancelled, try again.'),
                ]);
            }

            WorkNotifier::modified($deliveryNote, 'lines');
        });

        return $deliveryNoteItem->refresh();
    }

    private function hasOtherLiveItems(DeliveryNoteItem $deliveryNoteItem): bool
    {
        return DeliveryNoteItem::where('delivery_note_id', $deliveryNoteItem->delivery_note_id)
            ->whereKeyNot($deliveryNoteItem->id)
            ->whereRaw('COALESCE(quantity_not_picked, 0) < quantity_required')
            ->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('deliveryNoteItem')->deliveryNote->itemsCanBeCancelledBy($request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $request->user());
    }
}
