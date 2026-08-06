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
use App\Actions\Audits\DispatchSimpleAudit;
use App\Actions\Dispatching\Picking\Traits\AutoIgnoreZeroQuantityItems;

class StorePicking extends OrgAction
{
    use AutoIgnoreZeroQuantityItems;

    protected DeliveryNoteItem $deliveryNoteItem;
    private User|null $user = null;

    public function handle(DeliveryNoteItem $deliveryNoteItem, LocationOrgStock $locationOrgStock, array $modelData): Picking
    {
        $oldPickingQuantity = (int)($deliveryNoteItem->quantity_picked ?? 0);
        data_forget($modelData, 'location_org_stock_id');

        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'org_stock_id', $locationOrgStock->org_stock_id);
        data_set($modelData, 'location_id', $locationOrgStock->location_id);

        data_set($modelData, 'engine', PickingEngineEnum::AIKU, false);
        data_set($modelData, 'type', PickingTypeEnum::PICK, false);

        $type = $modelData['type'] instanceof PickingTypeEnum ? $modelData['type'] : PickingTypeEnum::from($modelData['type']);
        if (in_array($type, [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK], true)) {
            /*
             * Same clamp as SetAsWaitingWarehouse: a pick can never take the total picked
             * past what the delivery note item still needs, box splits go through
             * SplitPicking which keeps the total constant.
             */
            $outstanding = (float)$deliveryNoteItem->quantity_required
                - (float)$deliveryNoteItem->quantity_picked
                - (float)$deliveryNoteItem->quantity_waiting_warehouse
                - (float)$deliveryNoteItem->quantity_waiting_crm;

            if ($outstanding <= 0) {
                abort(422, 'Nothing left to pick: the required quantity is already picked or waiting');
            }

            $modelData['quantity'] = min((float)$modelData['quantity'], $outstanding);
            data_set($modelData, 'last_picked_at', now());
        }

        /** @var Picking $picking */
        $picking = $deliveryNoteItem->pickings()->create($modelData);
        $picking->refresh();

        if (app()->environment('production')) {
            SavePickingInAurora::dispatch($picking);
        }


        StoreOrgStockMovement::dispatch(
            $locationOrgStock->orgStock,
            $locationOrgStock->location,
            [
                'quantity' => -$picking->quantity,
                'type'     => OrgStockMovementTypeEnum::PICKED,
                'user_id'  => $this->user?->id,
            ],
            $picking
        );


        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);
        $deliveryNoteItem->refresh();
        $newPickingQuantity = (int)$deliveryNoteItem->quantity_picked;

        $productCode = $deliveryNoteItem->orgStock?->code ?? 'Unknown Item';

        $oldAuditString = "$oldPickingQuantity pcs of $productCode";
        $newAuditString = "$newPickingQuantity pcs of $productCode";

        DispatchSimpleAudit::run(
            auditableModel: $deliveryNoteItem->deliveryNote,
            logKey: 'picked_item',
            oldValue: $oldAuditString,
            newValue: $newAuditString,
            eventName: 'item_picked'
        );

        $this->ignoreZeroQuantityItems($deliveryNoteItem->deliveryNote, $this->user);

        return $picking;
    }

    public function rules(): array
    {
        return [
            'not_picked_reason'     => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'type'                  => ['sometimes', Rule::enum(PickingTypeEnum::class)],
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
