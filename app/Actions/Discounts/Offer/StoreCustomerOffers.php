<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jun 2026 15:44:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Models\Catalogue\Shop;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreCustomerOffers
{
    use AsAction;

    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Shop $shop, Request $request)
    {
        $data = $request->all();
        dd($data);

    }
}
