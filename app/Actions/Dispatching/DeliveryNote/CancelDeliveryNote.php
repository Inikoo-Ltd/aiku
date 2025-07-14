<?php
/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-17h-48m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/


namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemCancelStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class CancelDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        // TEMPORARY
        data_set($modelData, 'cancelled_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::CANCELLED);

        $deliveryNote = $this->update($deliveryNote, $modelData);

        if ($deliveryNote->pickings) {
                $deliveryNote->pickings()->delete();
                foreach ($deliveryNote->deliveryNoteItems as $item) {
                    StoreNotPickPicking::make()->action(
                        $item,
                        request()->user(),
                        [
                            'not_picked_reason' => PickingNotPickedReasonEnum::CANCELLED_BY_CUSTOMER,
                            'not_picked_note' => "Delivery Note #{$deliveryNote->reference} cancelled. Item will be returned.",
                            'quantity' => $item->quantity_required,
                        ]
                );
            }
        }

        foreach ($deliveryNote->deliveryNoteItems as $item) 
        {
            UpdateDeliveryNoteItem::make()->action($item, [
                'state' => DeliveryNoteItemStateEnum::CANCELLED,
                'cancel_state' => DeliveryNoteItemCancelStateEnum::RETURNED
            ]);
        }

        OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
            ->delay($this->hydratorsDelay);

        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
