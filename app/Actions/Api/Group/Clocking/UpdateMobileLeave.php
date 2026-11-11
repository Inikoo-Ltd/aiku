<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Leave\UpdateLeave;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileLeave
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        /** @var Leave $leave */
        $leave = $request->route('leave');
        /** @var User $user */
        $user = $request->user();

        return $user->employees()->whereKey($leave->employee_id)->exists();
    }

    public function asController(Leave $leave, ActionRequest $request): JsonResponse
    {
        $updatedLeave = UpdateLeave::make()->asController($request);

        return response()->json(['data' => (new GetMobileLeaves())->leaveData($updatedLeave)]);
    }
}
