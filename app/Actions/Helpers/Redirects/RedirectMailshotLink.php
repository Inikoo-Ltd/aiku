<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Comms\Mailshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectMailshotLink extends OrgAction
{
    public function handle(Mailshot $mailshot): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.marketing.mailshots.show', [
            $mailshot->organisation->slug,
            $mailshot->shop->slug,
            $mailshot->slug,
        ]));
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($mailshot->organisation, $request);

        return $this->handle($mailshot);
    }
}
