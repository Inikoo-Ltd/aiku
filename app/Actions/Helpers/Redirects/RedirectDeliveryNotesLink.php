<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:58:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectDeliveryNotesLink extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote): ?RedirectResponse
    {
        $url = route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
            $deliveryNote->organisation->slug,
            $deliveryNote->warehouse->slug,
            $deliveryNote->slug
        ]);
        return Redirect::to($url);
    }



    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

}
