<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackFetchStock extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): void
    {

        $stock = [];
        /** @var Portfolio $portfolio */
        foreach (Portfolio::where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)->get() as $portfolio) {
            /** @var Product $product */
            $product = $portfolio->item;
            $stock[$portfolio->sku] = $product->available_quantity;
        }


        print json_encode($stock);


    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        if (!$shopifyUser->customer_id) {
            abort(422);
        }

        $this->initialisation($shopifyUser->organisation, $request);

        $this->handle($shopifyUser);
    }
}
