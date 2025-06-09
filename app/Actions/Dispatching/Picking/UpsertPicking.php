<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpsertPicking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock, array $modelData): bool
    {

        $pickingID = Arr::pull($modelData, 'picking_id');
        $picking = null;
        if ($pickingID) {
            $picking = Picking::find($pickingID);
        }

        if ($picking) {
            $modelData = [
                'quantity' => Arr::get($modelData, 'quantity', 0),
            ];
            $picking = UpdatePicking::run($picking, $modelData);
        } else {
            $picking = StorePicking::run($deliveryNoteItem, $locationOrgStock, $modelData);
        }


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
