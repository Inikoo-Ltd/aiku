<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        if(Arr::get($modelData, 'quantity') > intval($deliveryNoteItem->quantity_required)) {
            throw ValidationException::withMessages([
                    'messages' => __('The quantity cannot be greater than the required quantity')
            ]);
        }

        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'org_stock_id', $deliveryNoteItem->org_stock_id);
        data_set($modelData, 'engine', PickingEngineEnum::AIKU);
        data_set($modelData, 'type', PickingTypeEnum::PICK);
        
        $picking = $deliveryNoteItem->pickings()->create($modelData);
        $picking->refresh();

        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);
        
        return $picking;
    }

    public function rules(): array
    {
        return [
            'not_picked_reason'         => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'engine'          => ['sometimes', Rule::enum(PickingEngineEnum::class)],
            'location_id'     => [
                'required',
                Rule::Exists('locations', 'id')->where('warehouse_id', $this->deliveryNoteItem->deliveryNote->warehouse_id)
            ],
            'quantity' => ['sometimes', 'numeric'],
            'picker_user_id'       => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function prepareForValidation($request)
    {
        if (!$request->has('picker_user_id')) {
            $this->set('picker_user_id', $request->user()->id);
        }
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

    public string $commandSignature = 'picking:store {deliveryNoteItem} {locationId} {userId} {quantity}';


    public function asCommand(Command $command)
    {
        $deliveryNoteItem = DeliveryNoteItem::findOrFail($command->argument('deliveryNoteItem'));

        $this->deliveryNoteItem = $deliveryNoteItem;

        $data = [
            'location_id'     => (int) $command->argument('locationId'),
            'picker_user_id'  => (int) $command->argument('userId'),
            'quantity'        => (int) $command->argument('quantity'),
        ];

        $picking = $this->handle($deliveryNoteItem, $data);

        $command->info("Picking created successfully with ID: {$picking->id}");

        return 1;
    }
}
