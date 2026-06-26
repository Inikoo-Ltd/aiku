<?php

/*
 * author Louis Perez
 * created on 21-05-2026-16h-08m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\WaitingItems\Json;

use App\Actions\Dispatching\WaitingItems\GetCrmReturnedBadgeData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCrmReturnedBadge
{
    use AsAction;

    public function handle(ActionRequest $request): array
    {
        return GetCrmReturnedBadgeData::run($request->user());
    }

    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->handle($request));
    }
}
