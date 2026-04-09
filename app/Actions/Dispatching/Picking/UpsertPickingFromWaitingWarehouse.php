<?php

/*
 * Author: Vika Aqordi
 * Created on 09-04-2026-16h-55m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpsertPickingFromWaitingWarehouse extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock, array $modelData): ?bool
    {
        // ###### File is copyed from UpsertPicking.php
        dd($modelData);

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
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->deliveryNoteItem->deliveryNote->warehouse_id)
            ],
            'quantity'              => ['required', 'numeric', 'min:0'],
            'picker_user_id' => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction && !$request->has('picker_user_id')) {
            $this->set('picker_user_id', $this->user->id);
        }
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->user             = $request->user();
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);
        $locationOrgStock = LocationOrgStock::find($this->validatedData['location_org_stock_id']);

        $this->handle($deliveryNoteItem, $locationOrgStock, $this->validatedData);
    }


}
