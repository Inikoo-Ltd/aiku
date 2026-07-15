<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectShippingZoneSchemaLink extends OrgAction
{
    public function handle(ShippingZoneSchema $shippingZoneSchema): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.billables.shipping.current', [
            $shippingZoneSchema->organisation->slug,
            $shippingZoneSchema->shop->slug,
            $shippingZoneSchema->slug,
        ]));
    }

    public function asController(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($shippingZoneSchema->organisation, $request);

        return $this->handle($shippingZoneSchema);
    }
}
