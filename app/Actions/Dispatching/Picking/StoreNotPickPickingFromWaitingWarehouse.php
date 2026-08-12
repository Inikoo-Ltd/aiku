<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 16:34:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\Dispatching\Picking\Traits\AutoIgnoreZeroQuantityItems;
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
    use AutoIgnoreZeroQuantityItems;

    private DeliveryNoteItem $deliveryNoteItem;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): ?Picking
    {
        data_set($modelData, 'picker_user_id', $user->id);


        return DB::transaction(function () use ($deliveryNoteItem, $modelData, $user) {
            /*
             * Only what is actually parked here can be taken out of it. Asking for the larger of the
             * two drained the bucket past empty and left it negative, and a negative bucket is added
             * back by every formula that subtracts it, so the screen then offered more than the
             * order ever required.
             */
            $quantityStillWaitingForWarehouse = (float)$deliveryNoteItem->quantity_waiting_warehouse;
            $quantityToNotPick                = min((float)Arr::get($modelData, 'quantity', 0), $quantityStillWaitingForWarehouse);

            if ($quantityToNotPick <= 0) {
                return null;
            }

            $newQuantityWaitingWarehouse = round($quantityStillWaitingForWarehouse - $quantityToNotPick, 6);

            $deliveryNoteItem->update([
                'quantity_waiting_warehouse' => $newQuantityWaitingWarehouse,
                'has_waiting_warehouse'      => $newQuantityWaitingWarehouse > 0,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);

            data_set($modelData, 'quantity', $quantityToNotPick);

            $picking = StoreNotPickPicking::make()->action($deliveryNoteItem, $user, $modelData);

            $this->ignoreZeroQuantityItems($deliveryNoteItem->deliveryNote, $user);

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
            $this->set('quantity', $this->deliveryNoteItem->quantity_waiting_warehouse);
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
