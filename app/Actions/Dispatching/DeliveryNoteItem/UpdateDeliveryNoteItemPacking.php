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
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Audits\DispatchSimpleAudit;


class UpdateDeliveryNoteItemPacking extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    protected User $user;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user): DeliveryNoteItem
    {

        $deliveryNote       = $deliveryNoteItem->deliveryNote;
        $oldState                 = $deliveryNote->state;

        $oldPackedQuantity  = (int) ($deliveryNoteItem->getOriginal('quantity_packed') ?? 0);

        StorePacking::make()->action($deliveryNoteItem, $this->user, []);
        $deliveryNoteItem->refresh();

        $newPackedQuantity = (int) $deliveryNoteItem->quantity_packed;
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
        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(fn($item) => empty((float)$item->quantity_not_picked) && $item->packings->count() == 0);


        if ($hasUnfinishedPackings->count() == 0) {
            UpdateDeliveryNoteStatePacked::make()->action($deliveryNote, $user);
        }

        return $deliveryNoteItem;
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
}
