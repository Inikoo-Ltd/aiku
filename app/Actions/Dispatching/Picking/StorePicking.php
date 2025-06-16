<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePicking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock, array $modelData): Picking
    {
        data_forget($modelData, 'location_org_stock_id');

        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'org_stock_id', $locationOrgStock->org_stock_id);
        data_set($modelData, 'location_id', $locationOrgStock->location_id);

        data_set($modelData, 'engine', PickingEngineEnum::AIKU, false);
        data_set($modelData, 'type', PickingTypeEnum::PICK);

        /** @var Picking $picking */
        $picking = $deliveryNoteItem->pickings()->create($modelData);
        $picking->refresh();


        StoreOrgStockMovement::run(
            $locationOrgStock->orgStock,
            $locationOrgStock->location,
            [
                'quantity' => -$picking->quantity,
                'type'     => OrgStockMovementTypeEnum::PICKED
            ]
        );

        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        return $picking;
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
            'quantity'              => ['required', 'numeric', 'min:0'],
            'picker_user_id'        => [
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

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): Picking
    {
        $this->asAction         = true;
        $this->user             = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);
        $locationOrgStock = LocationOrgStock::find($this->validatedData['location_org_stock_id']);

        return $this->handle($deliveryNoteItem, $locationOrgStock, $this->validatedData);
    }


}
