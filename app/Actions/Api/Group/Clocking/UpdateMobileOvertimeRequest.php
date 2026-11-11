<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Overtime\UpdateEmployeeOvertimeRequest;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileOvertimeRequest
{
    use AsController;

    public function authorize(ActionRequest $request): bool
    {
        /** @var OvertimeRequest $overtimeRequest */
        $overtimeRequest = $request->route('overtimeRequest');
        /** @var User $user */
        $user = $request->user();

        return $user->employees()->whereKey($overtimeRequest->employee_id)->exists();
    }

    public function asController(OvertimeRequest $overtimeRequest, ActionRequest $request): JsonResponse
    {
        $updatedOvertimeRequest = UpdateEmployeeOvertimeRequest::make()->asController($request, $overtimeRequest);
        $timezone               = $updatedOvertimeRequest->organisation->timezone?->name ?? config('app.timezone');

        return response()->json(['data' => (new GetMobileOvertimeRequests())->overtimeRequestData($updatedOvertimeRequest, $timezone)]);
    }
}
