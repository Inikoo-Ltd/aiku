<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Sowing;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Sowing\SowingEngineEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Sowing;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreSowing extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock, array $modelData): Sowing
    {
        data_forget($modelData, 'location_org_stock_id');

        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'org_stock_id', $locationOrgStock->org_stock_id);
        data_set($modelData, 'location_id', $locationOrgStock->location_id);

        data_set($modelData, 'engine', SowingEngineEnum::AIKU, false);
        data_set($modelData, 'sowed_at', now(), false);

        /** @var Sowing $sowing */
        $sowing = $deliveryNoteItem->sowings()->create($modelData);
        $sowing->refresh();

        // Create stock movement to return items to location
        // Note: For sowing (return), the quantity should be positive to add back to stock
        $orgStockMovement = StoreOrgStockMovement::run(
            $locationOrgStock->orgStock,
            $locationOrgStock->location,
            [
                'quantity' => abs($sowing->quantity), // Positive to add back to stock
                'type'     => OrgStockMovementTypeEnum::RETURN_PICKED
            ]
        );

        $sowing->update([
            'org_stock_movement_id' => $orgStockMovement->id,
        ]);

        return $sowing;
    }

    public function rules(): array
    {
        return [
            'engine'              => ['sometimes', Rule::enum(SowingEngineEnum::class)],
            'location_org_stock_id' => [
                'required',
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->deliveryNoteItem->deliveryNote->warehouse_id)
            ],
            'quantity'            => ['required', 'numeric'],
            'sower_user_id'       => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
            'original_picking_id' => ['sometimes', 'nullable', 'exists:pickings,id'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction && !$request->has('sower_user_id')) {
            $this->set('sower_user_id', $this->user->id);
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

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): Sowing
    {
        $this->asAction         = true;
        $this->user             = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);
        $locationOrgStock = LocationOrgStock::find($this->validatedData['location_org_stock_id']);

        return $this->handle($deliveryNoteItem, $locationOrgStock, $this->validatedData);
    }
}
