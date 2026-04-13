<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 11:04:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpsertPickingFromWaitingWarehouse extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, $user, array $modelData): ?bool
    {
        DB::transaction(function () use ($deliveryNoteItem, $user, $modelData) {
            $waitingWarehouseQuantity = $deliveryNoteItem->quantity_waiting_warehouse - Arr::get($modelData, 'quantity', 0);
            if ($waitingWarehouseQuantity < 0) {
                abort(400, 'Quantity waiting warehouse cannot be less than 0');
            }

            $deliveryNoteItem->update([
                'quantity_waiting_warehouse' => $waitingWarehouseQuantity,
                'has_waiting_warehouse'      => $waitingWarehouseQuantity > 0,
            ]);


            data_set($modelData, 'picker_user_id', $user->id);
            $locationOrgStock = LocationOrgStock::find(Arr::pull($modelData, 'location_org_stock_id'));
            StorePicking::run($deliveryNoteItem, $locationOrgStock, $modelData);
        });

        return true;
    }

    public function rules(): array
    {
        return [
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
        $this->initialisationFromWarehouse($deliveryNoteItem->deliveryNote->warehouse, $request);
        $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }


}
