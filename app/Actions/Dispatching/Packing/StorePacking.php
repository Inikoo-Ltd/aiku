<?php

/*
 * author Arya Permana - Kirin
 * created on 17-12-2024-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Dispatching\Packing;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPacked;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Packing\PackingEngineEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Packing;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePacking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): Packing
    {
        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'engine', PackingEngineEnum::AIKU);

        $packing = $deliveryNoteItem->packings()->create($modelData);

        CalculateDeliveryNoteItemTotalPacked::make()->action($deliveryNoteItem);

        $packing->refresh();

        return $packing;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'numeric'],
            'packer_user_id'       => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function prepareForValidation(ActionRequest $request)
    {
        if (!$request->has('packer_user_id')) {
            $this->set('packer_user_id', $request->user()->id);
        }
        if (!$request->has('quantity')) {
            $this->set('quantity', $this->deliveryNoteItem->quantity_picked);
        }
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request)
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, array $modelData): Packing
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
