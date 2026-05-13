<?php

/*
 * author Louis Perez
 * created on 12-03-2026-09h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Audits\DispatchSimpleAudit;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;

class UpdateDeliveryNoteItemPacking extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        $deliveryNote       = $deliveryNoteItem->deliveryNote;
        $oldState           = $deliveryNote->state;
        $oldPackedQuantity  = (int) ($deliveryNoteItem->getOriginal('quantity_packed') ?? 0);

        StorePacking::make()->action($deliveryNoteItem, $this->user, []);
        $deliveryNoteItem->refresh();

        $newPackedQuantity = (int) $deliveryNoteItem->quantity_packed;
        $productName = $deliveryNoteItem->data['name'] ?? $deliveryNoteItem->data['title'] ?? 'Unknown Item';

        $oldAuditString = "{$oldPackedQuantity} pcs of {$productName}";
        $newAuditString = "{$newPackedQuantity} pcs of {$productName}";

        DispatchSimpleAudit::run(
            auditableModel  : $deliveryNote,
            logKey          : 'packed_item_' . $deliveryNoteItem->id,
            oldValue        : $oldAuditString,
            newValue        : $newAuditString,
            eventName       : 'item_packed'
        );

        $siblingDeliveryNoteItems = $deliveryNote->deliveryNoteItems()->with('packings')->get();

        // Ignore deliveryNoteItem with quantity_not_picked
        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(fn ($item) => empty((float)$item->quantity_not_picked) && $item->packings->count() == 0);

        if ($oldState != DeliveryNoteStateEnum::PACKED && $hasUnfinishedPackings->count() == 0) {
            UpdateDeliveryNoteStatePacked::make()->action($deliveryNote, request()->user());
        }

        $deliveryNote = CheckAndCompleteDeliveryNotePacking::run($deliveryNote);

        return $deliveryNoteItem;
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user = $request->user();

        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
