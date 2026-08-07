<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-37m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\Picking\Traits\AutoIgnoreZeroQuantityItems;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
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
    use AutoIgnoreZeroQuantityItems;

    private Picking $picking;
    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(Picking $picking, array $modelData, ?User $user = null): Picking|bool
    {
        $this->user ??= $user;

        $oldQuantity = $picking->quantity;
        $oldType = $picking->type;

        if (Arr::has($modelData, 'quantity') && Arr::get($modelData, 'quantity') == 0) {
            return DeletePicking::make()->action($picking, $this->user);
        }

        if (Arr::has($modelData, 'quantity') && in_array($picking->type, [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK], true)) {
            $deliveryNoteItemForClamp = $picking->deliveryNoteItem;
            /* Outstanding includes this picking's own quantity, so any decrease is always allowed */
            $outstanding = (float)$deliveryNoteItemForClamp->quantity_required
                - ((float)$deliveryNoteItemForClamp->quantity_picked - (float)$picking->quantity)
                - (float)$deliveryNoteItemForClamp->quantity_waiting_warehouse
                - (float)$deliveryNoteItemForClamp->quantity_waiting_crm;

            if ($outstanding <= 0) {
                abort(422, 'Nothing left to pick: the required quantity is already picked or waiting');
            }

            $modelData['quantity'] = min((float)$modelData['quantity'], $outstanding);
        }

        $picking = $this->update($picking, $modelData);


        if ($picking->orgStockMovement) {

            if ($oldQuantity != $picking->quantity) {
                UpdateOrgStockMovement::make()->action($picking->orgStockMovement, [
                    'quantity' => -($picking->quantity),
                ]);
            }

        }


        /** @var DeliveryNoteItem $deliveryNoteItem */
        $deliveryNoteItem = $picking->deliveryNoteItem;


        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        $this->ignoreZeroQuantityItems($deliveryNoteItem->deliveryNote, $this->user);

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
        $this->user = $request->user();
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
