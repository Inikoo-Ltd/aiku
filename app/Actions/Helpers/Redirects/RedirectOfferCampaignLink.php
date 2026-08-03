<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOfferCampaignLink extends OrgAction
{
    public function handle(OfferCampaign $offerCampaign): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.discounts.campaigns.show', [
            $offerCampaign->organisation->slug,
            $offerCampaign->shop->slug,
            $offerCampaign->slug,
        ]));
    }

    public function asController(OfferCampaign $offerCampaign, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($offerCampaign->organisation, $request);

        return $this->handle($offerCampaign);
    }
}
