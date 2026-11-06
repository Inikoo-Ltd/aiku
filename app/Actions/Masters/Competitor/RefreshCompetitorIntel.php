<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\Competitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RefreshCompetitorIntel extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(Competitor $competitor): Competitor
    {
        FetchCompetitorIntel::dispatch($competitor);

        return $competitor;
    }

    public function asController(Competitor $competitor, ActionRequest $request): Competitor
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($competitor);
    }

    public function htmlResponse(Competitor $competitor): RedirectResponse
    {
        return Redirect::back();
    }
}
