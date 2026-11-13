<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Trolley\DetachTrolleyFromPickingSession;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DetachMobilePickingSessionTrolley
{
    use AsController;
    use WithMobilePickingSessions;

    public function asController(PickingSession $pickingSession, Trolley $trolley, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPickingSession($pickingSession, $user);
        $this->ensurePickingSessionState($pickingSession, $this->trolleyChangeStates());
        abort_unless($trolley->picking_session_id === $pickingSession->id, 404);

        DetachTrolleyFromPickingSession::make()->handle($trolley, $pickingSession);

        return $this->respondWithPickingSession($pickingSession, $user, 'done', __('Trolley removed.'));
    }
}
