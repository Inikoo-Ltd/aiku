<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

/**
 * Buy One Get One Free: buying the triggering product gives the same product for free.
 */
class StoreBogoOffer extends OrgAction
{
    public function asController(Shop $shop, ActionRequest $request): never
    {
        dd($request->all());
    }
}
