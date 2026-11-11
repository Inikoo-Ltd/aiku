<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Overtime\StoreEmployeeOvertimeRequest;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileOvertimeRequest
{
    use AsController;

    public function asController(ActionRequest $request): JsonResponse
    {
        $overtimeRequest = StoreEmployeeOvertimeRequest::make()->asController($request)->refresh();
        $timezone        = $overtimeRequest->organisation->timezone?->name ?? config('app.timezone');

        return response()->json(['data' => (new GetMobileOvertimeRequests())->overtimeRequestData($overtimeRequest, $timezone)], 201);
    }
}
