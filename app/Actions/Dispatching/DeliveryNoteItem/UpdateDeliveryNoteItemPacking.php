<?php

/*
 * author Louis Perez
 * created on 12-03-2026-09h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacking;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Audits\DispatchSimpleAudit;

class UpdateDeliveryNoteItemPacking extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;
    use HasDeliveryNoteHydrators;

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
            foreach ($deliveryNote->trolleys as $trolley) {
                DB::table('delivery_note_has_trolleys')
                    ->where('delivery_note_id', $deliveryNote->id)->where('trolley_id', $trolley->id)->delete();
                $trolley->update(['current_delivery_note_id' => null]);
            }

            $defaultParcel = count($deliveryNote->parcels) == 0 ? [
                [
                    'weight'     => $deliveryNote->effective_weight / 1000,
                    'dimensions' => [5, 5, 5]
                ]
            ] : $deliveryNote->parcels;

            UpdateDeliveryNote::make()->action($deliveryNote, [
                'packed_at'      => now(),
                'packer_user_id' => $this->user->id,
                'state'          => DeliveryNoteStateEnum::PACKED->value,
                'parcels'        => $defaultParcel
            ]);

            DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacking::make()->action($deliveryNote->orders->first());
            }
            $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
            $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKED);
        }


        return $deliveryNoteItem;
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user = $request->user();

        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
