<?php

/*
 * Author: Vika Aqordi
 * Created on 23-04-2026-11h-10m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\WaitingItems\Json;

use App\Actions\Dispatching\WaitingItems\GetCrmWaitingBadgeData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCrmWaitingBadge
{
    use AsAction;

    public function handle(ActionRequest $request): array
    {
        return GetCrmWaitingBadgeData::run($request->user());
    }

    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->handle($request));
    }
}
