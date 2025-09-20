<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ebay;

use App\Actions\Dropshipping\Ebay\Product\StoreNewProductToCurrentEbay;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreRetinaNewProductToCurrentEbay extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        StoreNewProductToCurrentEbay::run($portfolio->customerSalesChannel->user, $portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($request);
        $this->handle($portfolio);
    }

}
