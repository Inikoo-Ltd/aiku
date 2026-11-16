<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-15h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SetMobileDeliveryNoteAsPacked
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::PICKED, DeliveryNoteStateEnum::PACKING]);
        $this->ensureCanHandle($deliveryNote);

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
            if ($this->isDropshipping($deliveryNote)) {
                $this->refuseState(__('A dropshipping delivery note is not packed at the packing station.'));
            }
        } else {
            $this->ensurePackableWithoutPacking($deliveryNote);
        }

        $deliveryNote = $this->passingWebRefusals(
            fn () => UpdateDeliveryNoteStatePacked::make()->action($deliveryNote, $user),
            'state'
        );

        $message = $deliveryNote->refresh()->state == DeliveryNoteStateEnum::FINALISED
            ? __('Packed. The delivery note is back to finalised.')
            : __('Delivery note packed.');

        return $this->respondWith($deliveryNote, $user, 'done', $message);
    }

    private function ensurePackableWithoutPacking(DeliveryNote $deliveryNote): void
    {
        if (!$this->isDropshipping($deliveryNote)) {
            $this->refuseState(__('Only a dropshipping delivery note goes straight to packed; this one is packed from Packing.'));
        }

        if ($deliveryNote->hasIncompleteSets()) {
            $this->refuseState(__('A set sold only complete is missing a part. Put its other parts back first.'));
        }

        if (ShowDeliveryNote::hasUnhandledItems($deliveryNote)) {
            $this->refuseState(__('Every line has to be picked, marked as not picked or set as waiting first.'));
        }
    }
}
