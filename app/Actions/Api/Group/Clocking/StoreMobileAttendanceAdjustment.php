<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\AttendanceAdjustment\StoreAttendanceAdjustment;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileAttendanceAdjustment
{
    use AsController;

    public function asController(ActionRequest $request): JsonResponse
    {
        $adjustment = StoreAttendanceAdjustment::make()->asController($request);

        return response()->json(['data' => (new GetMobileAttendanceAdjustments())->adjustmentData($adjustment->refresh())], 201);
    }
}
