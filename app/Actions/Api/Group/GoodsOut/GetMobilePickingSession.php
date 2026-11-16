<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Models\Inventory\PickingSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobilePickingSession
{
    use AsController;
    use WithMobilePickingSessions;

    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPickingSession($pickingSession, $user);

        return response()->json(['data' => $this->pickingSessionPayload($pickingSession, $user)]);
    }
}
