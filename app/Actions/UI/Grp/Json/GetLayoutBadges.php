<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 18:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Json;

use App\Actions\UI\Grp\GetFirstLoadProps;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLayoutBadges
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json(GetFirstLoadProps::make()->badges($request->user()));
    }
}
