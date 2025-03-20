<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Webhook;

use App\Actions\Dropshipping\ShopifyUser\RegisterCustomerFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetupShopifyAccount extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'shopify:webhook {shopify}';

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, ?Fulfilment $fulfilment)
    {
        DB::transaction(function () use ($shopifyUser, $fulfilment) {
            $fulfilmentCustomer = $shopifyUser?->customer?->fulfilmentCustomer;
            if (!$fulfilmentCustomer && $fulfilment) {
                RegisterCustomerFromShopify::run($shopifyUser, $fulfilment);
            }
        });
    }

    public function asController(ShopifyUser $shopifyUser, ActionRequest $request)
    {
        $shop = Shop::find($request->input('shop'));

        $this->handle($shopifyUser, $shop->fulfilment);
    }
}
