<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickingSession\StorePickingSession;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobilePickingSession
{
    use AsController;
    use WithMobilePickingSessions;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($warehouse->group_id === $user->group_id && $warehouse->canBeWorkedInBy($user), 403, __('Only warehouse staff can work on picking sessions.'));

        $deliveryNoteIds = $this->validatedDeliveryNoteIds($request);
        $this->ensurePickingSessionCandidates($warehouse, $user, $deliveryNoteIds, true);

        $pickingSession = StorePickingSession::make()->handle($warehouse, [
            'delivery_notes' => $deliveryNoteIds,
            'user_id'        => $user->id,
        ]);

        return response()->json(['data' => $this->pickingSessionPayload(PickingSession::findOrFail($pickingSession->id), $user)], 201);
    }
}
