<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 16:34:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreNotPickPickingFromWaitingWarehouse extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNoteItem $deliveryNoteItem;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): ?Picking
    {
        data_set($modelData, 'picker_user_id', $user->id);


        return DB::transaction(function () use ($deliveryNoteItem, $modelData, $user) {
            $quantity = max(Arr::get($modelData, 'quantity', 0), $deliveryNoteItem->quantity_waiting_warehouse);

            $newQuantityWaitingWarehouse = $deliveryNoteItem->quantity_waiting_warehouse - $quantity;

            $deliveryNoteItem->update([
                'quantity_waiting_warehouse' => $newQuantityWaitingWarehouse,
                'has_waiting_warehouse'      => $newQuantityWaitingWarehouse > 0,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);


            $picking = StoreNotPickPicking::make()->action($deliveryNoteItem, $user, $modelData);
            AutoFinishWaitingDeliveryNote::run($deliveryNoteItem->deliveryNote);

            return $picking;
        });
    }

    public function rules(): array
    {
        return [
            'not_picked_reason' => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'   => ['sometimes', 'string'],
            'quantity'          => ['sometimes', 'numeric'],
        ];
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$request->has('quantity')) {
            $this->set('quantity', $this->deliveryNoteItem->quantity_required - $this->deliveryNoteItem->quantity_picked);
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): ?Picking
    {
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }


}
