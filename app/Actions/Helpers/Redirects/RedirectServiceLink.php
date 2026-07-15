<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Billables\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectServiceLink extends OrgAction
{
    public function handle(Service $service): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.billables.services.show', [
            $service->organisation->slug,
            $service->shop->slug,
            $service->slug,
        ]));
    }

    public function asController(Service $service, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($service->organisation, $request);

        return $this->handle($service);
    }
}
