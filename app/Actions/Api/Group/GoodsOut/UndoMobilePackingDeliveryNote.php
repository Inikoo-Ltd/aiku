<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoPackingDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UndoMobilePackingDeliveryNote
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
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::PACKING]);

        $deliveryNote = $this->passingWebRefusals(fn () => UndoPackingDeliveryNote::make()->action($deliveryNote, $user), 'state');

        $message = $deliveryNote->refresh()->state == DeliveryNoteStateEnum::HANDLING_BLOCKED
            ? __('Something on this delivery note is still waiting, so it is waiting now.')
            : __('Packing undone. The delivery note is picked again.');

        return $this->respondWith($deliveryNote, $user, 'done', $message);
    }
}
