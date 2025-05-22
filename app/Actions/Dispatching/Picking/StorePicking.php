<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingStateEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePicking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): Picking
    {
        if ($deliveryNoteItem->deliveryNote->state != DeliveryNoteStateEnum::HANDLING) {
            UpdateDeliveryNote::make()->action($deliveryNoteItem->deliveryNote, [
                'state' => DeliveryNoteStateEnum::HANDLING
            ]);
        }

        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'org_stock_id', $deliveryNoteItem->org_stock_id);
        data_set($modelData, 'quantity_required', $deliveryNoteItem->quantity_required);
        data_set($modelData, 'state', PickingStateEnum::PICKING);
        data_set($modelData, 'engine', PickingEngineEnum::AIKU);

        return $deliveryNoteItem->pickings()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'state'           => ['sometimes', Rule::enum(PickingStateEnum::class)],
            'not_picked_reason'         => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'engine'          => ['sometimes', Rule::enum(PickingEngineEnum::class)],
            'location_id'     => [
                'required',
                Rule::Exists('locations', 'id')->where('warehouse_id', $this->deliveryNoteItem->deliveryNote->warehouse_id)
            ],
            'quantity_picked' => ['sometimes', 'numeric'],
            'picker_id'       => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, array $modelData): Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
