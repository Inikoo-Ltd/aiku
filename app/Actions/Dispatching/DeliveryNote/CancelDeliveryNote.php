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
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Ordering\Order\RollbackOrderAfterDeliveryNoteCancellation;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemCancelStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CancelDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, $fromOrder = false): DeliveryNote
    {
        $cancelledRef = $deliveryNote->reference . '-CANCELLED';

        $cancelledCount = DB::table('delivery_notes')
                ->where('reference', 'like', $deliveryNote->reference . '-CANCELLED%')
                ->count();

        $newCancelledRef = $cancelledRef . ($cancelledCount > 0 ? '-' . ($cancelledCount + 1) : '');

        data_set($modelData, 'reference', $newCancelledRef);
        data_set($modelData, 'cancelled_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::CANCELLED);

        $deliveryNote = $this->update($deliveryNote, $modelData);

        foreach ($deliveryNote->pickings as $picking) {

            $deliveryNoteItem = $picking->deliveryNoteItem;

            $toPick = $deliveryNoteItem->quantity_required - $deliveryNoteItem->picked_quantity;

            $locationPickingStock = LocationOrgStock::where('location_id', $picking->location_id)
                ->where('org_stock_id', $picking->org_stock_id)->first();
            if (!$locationPickingStock) {
                $locationPickingStock = LocationOrgStock::where('org_stock_id', $picking->org_stock_id)->first();
            }
            // this needs to change if $locationPickingStock is null we need to throw the error to UI
            if ($locationPickingStock && $picking->type == PickingTypeEnum::PICK) {

                StorePicking::run(
                    $deliveryNoteItem,
                    $locationPickingStock,
                    [
                        'not_picked_reason' => PickingNotPickedReasonEnum::NA,
                        'type' => PickingTypeEnum::RETURN,
                        'quantity' => -$picking->quantity,
                        'picker_user_id' => $picking->picker_user_id,

                    ],
                );
            }

            if ($toPick > 0) {
                StoreNotPickPicking::make()->action(
                    $deliveryNoteItem,
                    request()->user(),
                    [
                        'not_picked_reason' => PickingNotPickedReasonEnum::CANCELLED_BY_CUSTOMER,
                        'not_picked_note' => "Delivery Note $deliveryNote->reference cancelled.",
                        'quantity' => $toPick,
                    ],
                );
            }
        }


        foreach ($deliveryNote->deliveryNoteItems as $item) {
            UpdateDeliveryNoteItem::make()->action($item, [
                'state' => DeliveryNoteItemStateEnum::CANCELLED,
                'cancel_state' => DeliveryNoteItemCancelStateEnum::RETURNED,
            ]);
        }

        if ($deliveryNote->type == DeliveryNoteTypeEnum::ORDER && $fromOrder == true) {
            $order = $deliveryNote->orders->first();
            RollbackOrderAfterDeliveryNoteCancellation::make()->action($order);
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

    public function action(DeliveryNote $deliveryNote, $fromOrder = false): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $fromOrder);
    }
}
