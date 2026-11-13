<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\SetAsPickedWithPickingBaysDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickedBay;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SetMobileDeliveryNoteAsPicked
{
    use AsController;
    use WithMobilePickingGuards;

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING]);
        $this->ensureCanHandle($deliveryNote);

        if ($this->isDropshipping($deliveryNote)) {
            $this->refuseState(__('A dropshipping delivery note is set as packed, not as picked.'));
        }

        if ($deliveryNote->hasIncompleteSets()) {
            $this->refuseState(__('A set sold only complete is missing a part. Put its other parts back first.'));
        }

        if (ShowDeliveryNote::hasUnhandledItems($deliveryNote)) {
            $this->refuseState(__('Every line has to be picked, marked as not picked or set as waiting first.'));
        }

        $data = Validator::make($request->all(), [
            'picked_bay_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $pickedBay = null;
        if (!empty($data['picked_bay_id'])) {
            $pickedBay = PickedBay::find($data['picked_bay_id']);
            abort_unless($pickedBay && $pickedBay->warehouse_id === $deliveryNote->warehouse_id, 404);
        }

        $deliveryNote = $this->passingWebRefusals(
            fn () => SetAsPickedWithPickingBaysDeliveryNote::run($deliveryNote, ['picked_bay' => $pickedBay?->id]),
            'state'
        );

        $message = $deliveryNote->refresh()->state == DeliveryNoteStateEnum::HANDLING_BLOCKED
            ? __('Something on this delivery note is still waiting, so it is waiting now.')
            : __('Set as picked.');

        return $this->respondWith($deliveryNote, $user, 'done', $message);
    }
}
