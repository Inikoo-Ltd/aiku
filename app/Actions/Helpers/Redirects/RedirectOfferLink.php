<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Discounts\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOfferLink extends OrgAction
{
    public function handle(Offer $offer): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.discounts.offers.show', [
            $offer->organisation->slug,
            $offer->shop->slug,
            $offer->slug,
        ]));
    }

    public function asController(Offer $offer, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($offer->organisation, $request);

        return $this->handle($offer);
    }
}
