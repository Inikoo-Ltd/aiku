<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The picker has put back on the shelf the parts of a set sold only complete whose other part was
 * not found (HELP-3548). Their picks are reversed, so the stock returns to its location, and the
 * parts are marked not picked, which releases the note and refunds the customer the whole product.
 */
class PutBackIncompleteSetParts extends OrgAction
{
    use WithDeliveryNoteWorkAuthorisation;
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, ?User $user): DeliveryNote
    {
        foreach ($deliveryNote->incompleteSetItems()->get() as $deliveryNoteItem) {
            $quantityToNotPick = $this->getQuantityToNotPick($deliveryNoteItem);

            if ($quantityToNotPick <= 0.000001) {
                continue;
            }

            $excess = $this->getQuantityToPutBack($deliveryNoteItem);
            if ($excess > 0.000001) {
                $this->reversePicks($deliveryNoteItem, $excess);
            }

            StoreNotPickPicking::make()->action($deliveryNoteItem->refresh(), $user, [
                'quantity'          => $quantityToNotPick,
                'not_picked_reason' => PickingNotPickedReasonEnum::CANCELLED_BY_WAREHOUSE,
                'not_picked_note'   => __('Put back: another part of the set was not found'),
            ]);
        }

        return AutoFinishWaitingDeliveryNote::run($deliveryNote->refresh());
    }

    public function getQuantityToPutBack(DeliveryNoteItem $deliveryNoteItem): float
    {
        return (float)$deliveryNoteItem->quantity_picked
            - (float)$deliveryNoteItem->quantity_required * $this->getCompleteSetFraction($deliveryNoteItem);
    }

    public function getQuantityToNotPick(DeliveryNoteItem $deliveryNoteItem): float
    {
        return (float)$deliveryNoteItem->quantity_required * (1 - $this->getCompleteSetFraction($deliveryNoteItem))
            - (float)$deliveryNoteItem->quantity_not_picked;
    }

    private function getCompleteSetFraction(DeliveryNoteItem $deliveryNoteItem): float
    {
        return (float)DeliveryNoteItem::where('delivery_note_id', $deliveryNoteItem->delivery_note_id)
            ->where('transaction_id', $deliveryNoteItem->transaction_id)
            ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->where('is_handled', true)
            ->where('quantity_required', '>', 0)
            ->get()
            ->min(fn (DeliveryNoteItem $part) => $part->quantity_picked / $part->quantity_required);
    }

    /**
     * @throws \Throwable
     */
    private function reversePicks(DeliveryNoteItem $deliveryNoteItem, float $excess): void
    {
        $pickings = $deliveryNoteItem->pickings()
            ->whereIn('type', [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK])
            ->orderByDesc('id')
            ->get();

        foreach ($pickings as $picking) {
            if ($excess <= 0.000001) {
                break;
            }
            $reduction = min($excess, (float)$picking->quantity);
            UpdatePicking::make()->action($picking, ['quantity' => (float)$picking->quantity - $reduction]);
            $excess -= $reduction;
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, ?User $user): DeliveryNote
    {
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $user);
    }
}
