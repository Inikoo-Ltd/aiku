<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetIrisInStockNewInProducts extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return GetIrisNewInProducts::run(shop: $this->shop, stockMode: 'in_stock');
    }
}
