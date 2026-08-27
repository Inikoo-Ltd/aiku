<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Aug 2026 13:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire\Json;

use App\Actions\Catalogue\Shop\External\Faire\GetFaireSkippedBadgeData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFaireSkippedBadge
{
    use AsAction;

    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(GetFaireSkippedBadgeData::run($request->user()));
    }
}
