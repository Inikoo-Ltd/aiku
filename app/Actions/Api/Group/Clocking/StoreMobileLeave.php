<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Leave\StoreLeave;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileLeave
{
    use AsController;

    public function asController(ActionRequest $request): JsonResponse
    {
        $leave = StoreLeave::make()->asController($request);

        return response()->json(['data' => (new GetMobileLeaves())->leaveData($leave->refresh())], 201);
    }
}
