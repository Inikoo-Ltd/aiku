<?php

/*
 * Author: Vika Aqordi
 * Created on 09-04-2026-16h-49m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class PickAllItemFromWaitingWarehouse extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): ?Picking
    {
        // ###### File is copyed from PickAllItem.php
        dd($modelData);

        // if ($deliveryNoteItem->locked_at && (Carbon::parse($deliveryNoteItem->locked_at)->diffInSeconds(now()) < 3)) {
        //     return null;
        // }

        // $deliveryNoteItem->update(['locked_at' => now()]);

        // try {
        //     $toPickQuantity   = $deliveryNoteItem->quantity_waiting_warehouse;
        //     $locationOrgStock = LocationOrgStock::find($modelData['location_org_stock_id']);

        //     data_set($modelData, 'quantity', min($toPickQuantity, $locationOrgStock->quantity));

        //     $picking = StorePicking::run($deliveryNoteItem, $locationOrgStock, $modelData);

        //     $deliveryNoteItem->refresh();
        //     $deliveryNoteItem->update([
        //         'locked_at'                  => null,
        //         'quantity_waiting_warehouse' => max(0, $deliveryNoteItem->quantity_waiting_warehouse - $picking->quantity),
        //     ]);

        //     DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);

        //     return $picking;
        // } catch (Exception $e) {
        //     $deliveryNoteItem->update(['locked_at' => null]);

        //     return null;
        // }
    }

    public function rules(): array
    {
        return [
            'not_picked_reason'     => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'engine'                => ['sometimes', Rule::enum(PickingEngineEnum::class)],
            'location_org_stock_id' => [
                'required',
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->deliveryNoteItem->deliveryNote->warehouse_id)
            ],
            'picker_user_id'        => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$request->has('picker_user_id')) {
            $this->set('picker_user_id', $request->user()->id);
        }
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, array $modelData): ?Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }


}
