<?php

/*
 * author Arya Permana - Kirin
 * created on 26-05-2025-14h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class PickAllItem extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): Picking
    {
        $toPickQuantity = $deliveryNoteItem->quantity_required - $deliveryNoteItem->quantity_picked;

        $locationOrgStock = LocationOrgStock::find($modelData['location_org_stock_id']);


        data_set($modelData, 'quantity', min($toPickQuantity, $locationOrgStock->quantity));

        return StorePicking::run($deliveryNoteItem, $locationOrgStock, $modelData);
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

    public function action(DeliveryNoteItem $deliveryNoteItem, array $modelData): Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }


}
