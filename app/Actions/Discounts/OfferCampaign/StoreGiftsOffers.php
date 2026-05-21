<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Apr 2026 16:01:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class StoreGiftsOffers extends OrgAction
{
    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Shop $shop, ActionRequest $request)
    {
        dd($request->all());


    }
}
