<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductForWooCommerce
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:wooCommerce:product:get {wooCommerceUser}';

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser)
    {
        $products = $wooCommerceUser->getWooCommerceProducts();

        if (!$products) {
            return collect([]);
        }

        dd($products);
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        return $this->handle($wooCommerceUser);
    }

    public function asCommand()
    {
        $this->handle(WooCommerceUser::first());
    }
}
