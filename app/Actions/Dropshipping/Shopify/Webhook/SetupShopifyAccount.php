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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class SetupShopifyAccount extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, Shop $shop)
    {
        DB::transaction(function () use ($shopifyUser, $shop) {
            if ($shop->type === ShopTypeEnum::DROPSHIPPING) {
                if (!$shopifyUser?->customer) {

                }
            } else {
                $fulfilment = $shop->fulfilment;
                $fulfilmentCustomer = $shopifyUser?->customer?->fulfilmentCustomer;
                if (!$fulfilmentCustomer && $fulfilment) {
                    RegisterCustomerFromShopify::run($shopifyUser, $fulfilment);
                }
            }
        });
    }

    /**
     * @throws \Exception
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request)
    {
        $shop = Shop::find($request->input('shop'));

        $this->handle($shopifyUser, $shop);
    }
}
