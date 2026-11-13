<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickingSession\AddDeliveryNotesToPickingSession;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AddMobilePickingSessionDeliveryNotes
{
    use AsController;
    use WithMobilePickingSessions;

    /**
     * @throws \Throwable
     */
    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPickingSession($pickingSession, $user);
        $this->ensurePickingSessionState($pickingSession, [PickingSessionStateEnum::IN_PROCESS]);

        $deliveryNoteIds = $this->validatedDeliveryNoteIds($request);
        $this->ensurePickingSessionCandidates($pickingSession->warehouse, $user, $deliveryNoteIds, false);

        AddDeliveryNotesToPickingSession::make()->handle($pickingSession, $deliveryNoteIds);

        return $this->respondWithPickingSession($pickingSession, $user, 'done', __('Delivery notes added.'));
    }
}
