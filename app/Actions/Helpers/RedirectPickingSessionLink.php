<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 10:44:54 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectPickingSessionLink extends OrgAction
{
    public function handle(PickingSession $deliveryNote): ?RedirectResponse
    {
        $url = route('grp.org.warehouses.show.dispatching.picking_sessions.show', [
            $deliveryNote->organisation->slug,
            $deliveryNote->warehouse->slug,
            $deliveryNote->slug
        ]);
        return Redirect::to($url);
    }



    public function asController(PickingSession $pickingSession, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($pickingSession->organisation, $request);
        return $this->handle($pickingSession);
    }

}
