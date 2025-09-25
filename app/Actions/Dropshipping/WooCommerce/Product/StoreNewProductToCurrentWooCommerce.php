<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreNewProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): void
    {
        StoreWooCommerceProduct::run($wooCommerceUser, $portfolio);
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $portfolio->customerSalesChannel->user;
        $this->initialisation($portfolio->organisation, $request);

        $this->handle($wooCommerceUser, $portfolio);
    }
}
