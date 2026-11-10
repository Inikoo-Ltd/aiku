<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 18:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Json;

use App\Actions\UI\Grp\GetFirstLoadProps;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLayoutOrganisationNavigation
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return GetFirstLoadProps::make()->isAuthorisedFor($request->user(), $request->route('organisation'));
    }

    public function asController(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        return response()->json([
            'navigation' => GetFirstLoadProps::make()->organisationNavigation($request->user(), $organisation),
        ]);
    }
}
