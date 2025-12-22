<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDeliveryItem;
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

    protected DeliveryNoteItem|StockDeliveryItem $parent;
    protected User $user;

    public function handle(DeliveryNoteItem|StockDeliveryItem $parent, LocationOrgStock $locationOrgStock, array $modelData): Sowing
    {
        if ($parent instanceof DeliveryNoteItem) {
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'delivery_note_id', $parent->delivery_note_id);
            $orgStockMovement = OrgStockMovementTypeEnum::RETURN_PICKED;
        } else {
            data_set($modelData, 'stock_delivery_id', $parent->stock_delivery_id);
            $orgStockMovement = OrgStockMovementTypeEnum::PURCHASE;
        }

        data_forget($modelData, 'location_org_stock_id');
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'org_stock_id', $locationOrgStock->org_stock_id);
        data_set($modelData, 'location_id', $locationOrgStock->location_id);

        data_set($modelData, 'sowed_at', now(), false);

        /** @var Sowing $sowing */
        $sowing = $parent->sowings()->create($modelData);
        $sowing->refresh();


        $orgStockMovement = StoreOrgStockMovement::run(
            $locationOrgStock->orgStock,
            $locationOrgStock->location,
            [
                'quantity' => $sowing->quantity,
                'type'     => $orgStockMovement
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
            'location_org_stock_id' => [
                'required',
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->parent->deliveryNote->warehouse_id)
            ],
            'quantity'              => ['required', 'numeric', 'gt:0'],
            'sower_user_id'         => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
            'original_picking_id'   => ['sometimes', 'nullable', 'exists:pickings,id'],
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
        $this->user = $request->user();
        $this->parent = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);
        $locationOrgStock = LocationOrgStock::find($this->validatedData['location_org_stock_id']);

        $this->handle($deliveryNoteItem, $locationOrgStock, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): Sowing
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->parent   = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);
        $locationOrgStock = LocationOrgStock::find($this->validatedData['location_org_stock_id']);

        return $this->handle($deliveryNoteItem, $locationOrgStock, $this->validatedData);
    }
}
