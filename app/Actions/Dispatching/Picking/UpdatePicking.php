<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdatePicking extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private Picking $picking;

    public function handle(Picking $picking, array $modelData): Picking|bool
    {
        if (Arr::get($modelData, 'quantity') == 0) {
            return DeletePicking::make()->action($picking);
        }

        $picking = $this->update($picking, $modelData);

        /** @var DeliveryNoteItem $deliveryNoteItem */
        $deliveryNoteItem = $picking->deliveryNoteItem;

        $totalPicked = $deliveryNoteItem->pickings()->where('type', PickingTypeEnum::PICK)->sum('quantity');

        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        return $picking;
    }

    public function rules(): array
    {
        return [
            'type'              => ['sometimes', Rule::enum(PickingTypeEnum::class)],
            'not_picked_reason'  => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'    => ['sometimes', 'string'],
            'quantity' => ['sometimes', 'numeric'],
        ];
    }

    public function asController(Picking $picking, ActionRequest $request): Picking
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $request);

        return $this->handle($picking, $this->validatedData);
    }

    public function action(Picking $picking, array $modelData): Picking
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $modelData);

        return $this->handle($picking, $this->validatedData);
    }
}
