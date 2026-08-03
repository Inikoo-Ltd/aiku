<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectWebpageLink extends OrgAction
{
    public function handle(Webpage $webpage): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.web.webpages.show', [
            $webpage->organisation->slug,
            $webpage->shop->slug,
            $webpage->website->slug,
            $webpage->slug,
        ]));
    }

    public function asController(Webpage $webpage, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }
}
