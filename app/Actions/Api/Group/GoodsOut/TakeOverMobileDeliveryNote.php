<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class TakeOverMobileDeliveryNote
{
    use AsController;
    use WithMobilePickingGuards;

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::PACKING]);

        if ($this->canHandleDeliveryNote($deliveryNote)) {
            $this->refuseState(__('You are already handling this delivery note.'));
        }

        self::claimMobileDeliveryNoteHandling($deliveryNote, $user);

        return $this->respondWith($deliveryNote, $user, 'done', $deliveryNote->state == DeliveryNoteStateEnum::PACKING
            ? __('You can work on this delivery note for the next hour. The packer stays the same.')
            : __('You can work on this delivery note for the next hour. The picker stays the same.'));
    }
}
