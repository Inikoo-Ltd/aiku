<?php

/*
 * Author: Vika Aqordi
 * Created on 23-04-2026-11h-09m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\UI\Json;

use App\Actions\Dispatching\UI\GetDispatchingWaitingBadgeData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDispatchingWaitingBadge
{
    use AsAction;

    public function handle(ActionRequest $request): array
    {
        return GetDispatchingWaitingBadgeData::run($request->user());
    }

    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->handle($request));
    }
}
