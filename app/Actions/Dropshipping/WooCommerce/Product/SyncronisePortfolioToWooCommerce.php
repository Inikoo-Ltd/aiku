<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncronisePortfolioToWooCommerce extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio)
    {
        RequestApiUploadProductWooCommerce::dispatch($wooCommerceUser, $portfolio);
    }

    public function asController(WooCommerceUser $wooCommerceUser, Portfolio $portfolio, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($wooCommerceUser, $portfolio);
    }
}
