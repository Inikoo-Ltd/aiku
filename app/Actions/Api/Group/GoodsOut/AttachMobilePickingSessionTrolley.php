<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Trolley\AttachTrolleyToPickingSession;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AttachMobilePickingSessionTrolley
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
        $this->ensurePickingSessionState($pickingSession, $this->trolleyChangeStates());

        $data = Validator::make($request->all(), [
            'trolley_id' => ['required', 'integer'],
        ])->validate();

        /** @var Trolley|null $trolley */
        $trolley = Trolley::find($data['trolley_id']);
        abort_unless($trolley && $trolley->warehouse_id === $pickingSession->warehouse_id, 404);

        if ($trolley->picking_session_id === $pickingSession->id) {
            return $this->respondWithPickingSession($pickingSession, $user, 'nothing_changed', __('The trolley is already on this picking session.'));
        }

        AttachTrolleyToPickingSession::make()->handle($trolley, $pickingSession);

        return $this->respondWithPickingSession($pickingSession, $user, 'done', __('Trolley added.'));
    }
}
