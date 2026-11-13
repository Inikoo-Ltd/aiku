<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickingSession\UndoWaitingPickingSession;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UndoMobileWaitingPickingSession
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
        $this->ensurePickingSessionState($pickingSession, [PickingSessionStateEnum::HANDLING_BLOCKED]);
        abort_unless(UndoWaitingPickingSession::canStepBack($user, $pickingSession), 403, __('Only the picker of this picking session or a dispatch supervisor can send it back to picking.'));

        UndoWaitingPickingSession::make()->action($pickingSession);

        return $this->respondWithPickingSession($pickingSession, $user, 'done', __('The picking session is back to picking.'));
    }
}
