<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\AttendanceAdjustment\CancelAttendanceAdjustment;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class CancelMobileAttendanceAdjustment
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        /** @var AttendanceAdjustment $adjustment */
        $adjustment = $request->route('adjustment');
        /** @var User $user */
        $user = $request->user();

        return $user->employees()->whereKey($adjustment->employee_id)->exists();
    }

    public function asController(AttendanceAdjustment $adjustment, ActionRequest $request): JsonResponse
    {
        CancelAttendanceAdjustment::make()->asController($adjustment, $request);

        return response()->json([
            'data' => [
                'id'        => $adjustment->id,
                'cancelled' => true,
            ],
        ]);
    }
}
