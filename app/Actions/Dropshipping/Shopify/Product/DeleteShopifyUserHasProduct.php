<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteShopifyUserHasProduct extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(?Portfolio $portfolio, bool $forceDelete = false, bool $fromWebhook = false): ShopifyUserHasProduct|null|int
    {
        if (!$portfolio) {
            return null;
        }

        if (!$fromWebhook && $portfolio->platform_product_id) {
            /** @var ShopifyUser $shopifyUser */
            $shopifyUser = $portfolio->customerSalesChannel->user;
            $shopifyUser->getShopifyClient()->request('DELETE', '/admin/api/2025-04/products/'.$portfolio->platform_product_id.'.json');
        }

        return null;
    }
}
