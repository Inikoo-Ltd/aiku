<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 11:04:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\Ordering\Transaction\Traits\WithCalculateTransactionDiscount;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpsertPickingFromWaitingWarehouse extends OrgAction
{
    use WithCalculateTransactionDiscount;
    /**
     * @var \App\Models\Dispatching\DeliveryNoteItem
     */
    private DeliveryNoteItem $deliveryNoteItem;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, $user, array $modelData): ?bool
    {
        DB::transaction(function () use ($deliveryNoteItem, $user, $modelData) {
            $waitingWarehouseQuantity = $deliveryNoteItem->quantity_required
                - Arr::get($modelData, 'quantity', 0)
                - $deliveryNoteItem->quantity_waiting_crm
                - $deliveryNoteItem->quantity_not_picked;

            if ($waitingWarehouseQuantity < 0) {
                $waitingWarehouseQuantity = 0;
            }

            $deliveryNoteItem->update([
                'quantity_waiting_warehouse' => $waitingWarehouseQuantity,
                'has_waiting_warehouse'      => $waitingWarehouseQuantity > 0,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);


            data_set($modelData, 'picker_user_id', $user->id);
            $locationOrgStock = LocationOrgStock::find(Arr::pull($modelData, 'location_org_stock_id'));


            $pickingID = Arr::pull($modelData, 'picking_id');
            $picking   = null;
            if ($pickingID) {
                $picking = Picking::find($pickingID);
            }

            if ($picking) {
                $modelData = [
                    'quantity' => Arr::get($modelData, 'quantity', 0),
                ];
                UpdatePicking::run($picking, $modelData);
            } else {
                StorePicking::run($deliveryNoteItem, $locationOrgStock, $modelData);
            }

            AutoFinishWaitingDeliveryNote::run($deliveryNoteItem->deliveryNote);
            
            // To fix concurrent issue discount not applied after picking up from Waiting (reported by Erika)
            $this->calculateTransactionDiscountTotal($deliveryNoteItem->transaction);
        });

        return true;
    }

    public function rules(): array
    {
        return [
            'picking_id'            => [
                'nullable',
                Rule::Exists('pickings', 'id')->where('delivery_note_item_id', $this->deliveryNoteItem->id)
            ],
            'location_org_stock_id' => [
                'required',
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->warehouse->id)
            ],
            'quantity'              => ['required', 'numeric', 'min:0'],
        ];
    }


    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {

        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromWarehouse($deliveryNoteItem->deliveryNote->warehouse, $request);
        $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }


}
