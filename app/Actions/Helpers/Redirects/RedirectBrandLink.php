<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Helpers\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectBrandLink extends OrgAction
{
    public function handle(Brand $brand): RedirectResponse
    {
        return Redirect::to(route('grp.trade_units.brands.show', [$brand->slug]));
    }

    public function asController(Brand $brand, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($brand);
    }
}
