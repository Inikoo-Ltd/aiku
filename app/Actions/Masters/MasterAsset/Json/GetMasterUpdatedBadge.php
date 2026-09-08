<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\Masters\MasterAsset\GetMasterUpdatedBadgeData;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMasterUpdatedBadge
{
    use AsAction;

    public function handle(ActionRequest $request): array
    {
        return GetMasterUpdatedBadgeData::run($request->user());
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($request));
    }
}
