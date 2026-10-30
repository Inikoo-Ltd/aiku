<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class GetLiveVisitorTrail extends OrgAction
{
    use WithWebAuthorisation;

    public function handle(Website $website, string $sessionId): array
    {
        return TrackWebsiteVisitorActivity::make()->getTrail($website, $sessionId);
    }

    public function asController(Website $website, string $sessionId, ActionRequest $request): array
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, $sessionId);
    }

    public function jsonResponse(array $trail): array
    {
        return $trail;
    }
}
