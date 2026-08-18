<?php

/*
 * author Louis Perez
 * created on 12-03-2026-09h-47m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Audits\DispatchSimpleAudit;

class UpdateDeliveryNoteItemPacking extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    protected User $user;

    /**
     * A packer may pack an item in several goes, so packings accumulate towards the picked quantity
     * and $quantity defaults to whatever is still left to pack.
     *
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, ?float $quantity = null): DeliveryNoteItem
    {
        $deliveryNote = $deliveryNoteItem->deliveryNote;

        $oldPackedQuantity = (float)($deliveryNoteItem->getOriginal('quantity_packed') ?? 0);

        // Quantities accumulate, so reading what is left and writing the new packing has to be
        // atomic. Two packers scanning the same item would otherwise both see the same remainder
        // and pack it twice.
        DB::transaction(function () use ($deliveryNoteItem, $user, $quantity) {
            DeliveryNoteItem::whereKey($deliveryNoteItem->id)->lockForUpdate()->first();

            $quantity = $this->resolveQuantityToPack($deliveryNoteItem, $quantity);

            StorePacking::make()->action($deliveryNoteItem, $user, ['quantity' => $quantity]);
        });

        $deliveryNoteItem->refresh();

        $newPackedQuantity = (float)$deliveryNoteItem->quantity_packed;
        $productName = $deliveryNoteItem->data['name'] ?? $deliveryNoteItem->data['title'] ?? 'Unknown Item';

        $oldAuditString = "$oldPackedQuantity pcs of $productName";
        $newAuditString = "$newPackedQuantity pcs of $productName";

        DispatchSimpleAudit::run(
            auditableModel  : $deliveryNote,
            logKey          : 'packed_item_' . $deliveryNoteItem->id,
            oldValue        : $oldAuditString,
            newValue        : $newAuditString,
            eventName       : 'item_packed'
        );


        $siblingDeliveryNoteItems = $deliveryNote->deliveryNoteItems()->with('packings')->get();
        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(
            fn (DeliveryNoteItem $item) => empty((float)$item->quantity_not_picked)
                && !static::isFullyPacked($item)
        );


        /*
         * An item waiting for a replacement decision or warehouse release has quantity_picked 0,
         * which isFullyPacked() reads as complete. Without this check, scanning the last real item
         * silently packs a note whose waiting lines were never picked, zeroing them on the order.
         */
        $hasWaitingItems = $siblingDeliveryNoteItems->contains(
            fn (DeliveryNoteItem $item) => $item->has_waiting_warehouse || $item->has_waiting_crm
        );

        if ($hasUnfinishedPackings->count() == 0 && !$hasWaitingItems) {
            UpdateDeliveryNoteStatePacked::make()->action($deliveryNote, $user);
        }

        return $deliveryNoteItem;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function resolveQuantityToPack(DeliveryNoteItem $deliveryNoteItem, ?float $quantity): float
    {
        $remaining = static::quantityLeftToPack($deliveryNoteItem);

        if ($quantity === null) {
            $quantity = $remaining;
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Quantity to pack must be greater than zero'),
            ]);
        }

        if (round($quantity, 3) > round($remaining, 3)) {
            throw ValidationException::withMessages([
                'quantity' => __('Only :remaining left to pack out of :picked picked', [
                    'remaining' => $remaining,
                    'picked'    => (float)$deliveryNoteItem->quantity_picked,
                ]),
            ]);
        }

        return $quantity;
    }

    public static function quantityLeftToPack(DeliveryNoteItem $deliveryNoteItem): float
    {
        $alreadyPacked = (float)$deliveryNoteItem->packings()->sum('quantity');

        return max(0, round((float)$deliveryNoteItem->quantity_picked - $alreadyPacked, 3));
    }

    public static function isFullyPacked(DeliveryNoteItem $deliveryNoteItem): bool
    {
        $alreadyPacked = (float)$deliveryNoteItem->packings->sum('quantity');

        return round($alreadyPacked, 3) >= round((float)$deliveryNoteItem->quantity_picked, 3);
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user = $request->user();

        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $request->user());
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, ?float $quantity = null): DeliveryNoteItem
    {
        $this->user = $user;

        $this->initialisationFromShop($deliveryNoteItem->shop, []);

        return $this->handle($deliveryNoteItem, $user, $quantity);
    }
}
