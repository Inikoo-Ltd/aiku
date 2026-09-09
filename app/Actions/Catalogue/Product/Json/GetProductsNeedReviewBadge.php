<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\Catalogue\Product\GetProductsNeedReviewBadgeData;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductsNeedReviewBadge
{
    use AsAction;

    public function handle(ActionRequest $request): array
    {
        return GetProductsNeedReviewBadgeData::run($request->user());
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($request));
    }
}
