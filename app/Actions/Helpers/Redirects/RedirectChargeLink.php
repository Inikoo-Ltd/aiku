<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Billables\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectChargeLink extends OrgAction
{
    public function handle(Charge $charge): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.billables.charges.show', [
            $charge->organisation->slug,
            $charge->shop->slug,
            $charge->slug,
        ]));
    }

    public function asController(Charge $charge, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($charge->organisation, $request);

        return $this->handle($charge);
    }
}
