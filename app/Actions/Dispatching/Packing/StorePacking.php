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
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePacking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): Packing
    {
        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'engine', PackingEngineEnum::AIKU);

        /*
         * Note-level timestamps come from the delivery note so every packing line of a note
         * carries identical values: queued_at is when picking finished (note became packable),
         * packing_at is when a packer started the note. done_at is genuinely per line.
         */
        $deliveryNote = $deliveryNoteItem->deliveryNote;
        data_set($modelData, 'queued_at', $deliveryNote->picked_at ?? now());
        data_set($modelData, 'packing_at', $deliveryNote->packing_at ?? now());
        data_set($modelData, 'done_at', now());

        $packing = $deliveryNoteItem->packings()->create($modelData);

        CalculateDeliveryNoteItemTotalPacked::make()->action($deliveryNoteItem);

        $packing->refresh();

        return $packing;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'numeric'],
            'data'     => ['sometimes', 'array'],
            'packer_user_id'       => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    /**
     * Defaults are read from the action's own attributes rather than the request, so a quantity
     * handed over by a calling action is honoured instead of being overwritten by the full picked
     * quantity just because the surrounding HTTP request carries no quantity field.
     */
    public function prepareForValidation(): void
    {
        if (!$this->has('packer_user_id')) {
            $this->set('packer_user_id', $this->user->id);
        }
        if (!$this->has('quantity')) {
            $this->set('quantity', $this->deliveryNoteItem->quantity_picked);
        }
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request)
    {
        $this->user = $request->user();
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): Packing
    {
        $this->user = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}
