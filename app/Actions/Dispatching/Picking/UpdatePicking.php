<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-37m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
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

    /**
     * @throws \Throwable
     */
    public function handle(Picking $picking, array $modelData): Picking|bool
    {
        $oldQuantity = $picking->quantity;
        $oldType = $picking->type;

        if (Arr::has($modelData, 'quantity') && Arr::get($modelData, 'quantity') == 0) {
            return DeletePicking::make()->action($picking, null);
        }

        $picking = $this->update($picking, $modelData);


        if($picking->orgStockMovement){

            if($oldQuantity!=$picking->quantity){
                UpdateOrgStockMovement::make()->action($picking->orgStockMovement, [
                    'quantity' => $picking->quantity,
                ]);
            }

        }


        /** @var DeliveryNoteItem $deliveryNoteItem */
        $deliveryNoteItem = $picking->deliveryNoteItem;


        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        return $picking;
    }

    public function rules(): array
    {
        return [
            'type'              => ['sometimes', Rule::enum(PickingTypeEnum::class)],
            'not_picked_reason' => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'   => ['sometimes', 'string'],
            'quantity'          => ['sometimes', 'numeric'],
            'batch_code_id'     => ['sometimes', 'nullable', 'integer', 'exists:batch_codes,id'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Picking $picking, ActionRequest $request): void
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $request);

        $this->handle($picking, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Picking $picking, array $modelData): Picking|bool
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $modelData);

        return $this->handle($picking, $this->validatedData);
    }
}
