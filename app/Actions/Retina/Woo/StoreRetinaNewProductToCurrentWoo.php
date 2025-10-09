<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Woo;

use App\Actions\Dropshipping\WooCommerce\Product\StoreNewProductToCurrentWooCommerce;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreRetinaNewProductToCurrentWoo extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        StoreNewProductToCurrentWooCommerce::run($portfolio->customerSalesChannel->user, $portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($request);
        $this->handle($portfolio);
    }

}
