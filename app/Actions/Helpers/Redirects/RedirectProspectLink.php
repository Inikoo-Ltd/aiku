<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\CRM\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectProspectLink extends OrgAction
{
    public function handle(Prospect $prospect): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.crm.prospects.show', [
            $prospect->organisation->slug,
            $prospect->shop->slug,
            $prospect->slug,
        ]));
    }

    public function asController(Prospect $prospect, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($prospect->organisation, $request);

        return $this->handle($prospect);
    }
}
