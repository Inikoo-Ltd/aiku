<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreNotPickPicking extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private DeliveryNoteItem $deliveryNoteItem;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): Picking
    {
        data_set($modelData, 'group_id', $deliveryNoteItem->group_id);
        data_set($modelData, 'organisation_id', $deliveryNoteItem->organisation_id);
        data_set($modelData, 'shop_id', $deliveryNoteItem->shop_id);
        data_set($modelData, 'delivery_note_id', $deliveryNoteItem->delivery_note_id);
        data_set($modelData, 'org_stock_id', $deliveryNoteItem->org_stock_id);
        data_set($modelData, 'engine', PickingEngineEnum::AIKU);
        data_set($modelData, 'type', PickingTypeEnum::NOT_PICK);

        $picking = $deliveryNoteItem->pickings()->create($modelData);
        $picking->refresh();

        CalculateDeliveryNoteItemTotalPicked::make()->action($picking->deliveryNoteItem);

        $this->update($deliveryNoteItem, [
            'is_completed' => true
        ]);

        return $picking;

    }

    public function rules(): array
    {
        return [
            'not_picked_reason'  => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'    => ['sometimes', 'string'],
            'quantity' => ['sometimes', 'numeric'],
            'picker_user_id'       => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->deliveryNoteItem->quantity_required == $this->deliveryNoteItem->quantity_picked || $this->deliveryNoteItem->is_completed) {
            throw ValidationException::withMessages([
                    'messages' => __('This delivery note item has been fully picked')
                ]);
        }
        if (!$request->has('picker_user_id')) {
            $this->set('picker_user_id', $request->user()->id);
        }
        if (!$request->has('quantity')) {
            $this->set('quantity', $this->deliveryNoteItem->quantity_required - $this->deliveryNoteItem->quantity_picked);
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

    public string $commandSignature = 'not-picking:store {deliveryNoteItem} {locationId} {userId} {quantity}';


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

        $command->info("Picking type NOT PICK created successfully with ID: {$picking->id}");

        return 1;
    }
}
