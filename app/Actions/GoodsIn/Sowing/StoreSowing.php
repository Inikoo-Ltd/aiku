<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreSowing extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem|StockDeliveryItem|ReturnDeliveryNoteItem $parent;
    protected ?User $user;

    public function handle(DeliveryNoteItem|StockDeliveryItem|ReturnDeliveryNoteItem $parent, array $modelData): Sowing
    {
        $locationOrgStock   = null;
        $locationOrgStockId = Arr::pull($modelData, 'location_org_stock_id', null);
        if ($locationOrgStockId) {
            $locationOrgStock = LocationOrgStock::find($locationOrgStockId);
        }

        if ($parent instanceof DeliveryNoteItem) {
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'delivery_note_id', $parent->delivery_note_id);
            $orgStockMovement = OrgStockMovementTypeEnum::RETURN_PICKED;
        } elseif ($parent instanceof ReturnDeliveryNoteItem) {
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'return_id', $parent->return_delivery_note_id);
            data_set($modelData, 'return_item_id', $parent->id);
            $orgStockMovement = OrgStockMovementTypeEnum::RETURN_PICKED;
        } else {
            data_set($modelData, 'stock_delivery_id', $parent->stock_delivery_id);
            $orgStockMovement = OrgStockMovementTypeEnum::PURCHASE;
        }

        // data_forget($modelData, 'location_org_stock_id');
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'org_stock_id', $parent->org_stock_id);

        data_set($modelData, 'location_id', $locationOrgStock?->location_id);

        data_set($modelData, 'sowed_at', now(), false);

        $sowType = data_get($modelData, 'type', SowingTypeEnum::SOW);

        /** @var Sowing $sowing */
        $sowing = $parent->sowings()->create($modelData);
        $sowing->refresh();

        if ($sowType === SowingTypeEnum::SOW && $locationOrgStock) {
            $orgStockMovement = StoreOrgStockMovement::run(
                $locationOrgStock?->orgStock,
                $locationOrgStock?->location,
                [
                    'quantity' => $sowing->quantity,
                    'type'     => $orgStockMovement
                ]
            );

            $sowing->update([
                'org_stock_movement_id' => $orgStockMovement->id,
            ]);
        }


        return $sowing;
    }

    public function rules(): array
    {
        if ($this->parent instanceof DeliveryNoteItem) {
            $warehouseId = $this->parent->deliveryNote->warehouse_id;
        } else {
            $warehouseId = $this->parent->returnDeliveryNote->warehouse_id;
        }

        return [
            'location_org_stock_id' => [
                'sometimes',
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $warehouseId)
            ],
            'quantity'              => ['required', 'numeric', 'gt:0'],
            'sower_user_id'         => [
                'sometimes',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
            'original_picking_id'   => ['sometimes', 'nullable', 'exists:pickings,id'],
            'type'                  => ['sometimes', Rule::enum(SowingTypeEnum::class)],
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
        $this->user   = $request->user();
        $this->parent = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(ReturnDeliveryNoteItem|DeliveryNoteItem $deliveryNoteItem, ?User $user, array $modelData): Sowing
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->parent   = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
