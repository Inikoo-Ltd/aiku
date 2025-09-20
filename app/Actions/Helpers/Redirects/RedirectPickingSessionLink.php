<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:58:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

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
