<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteProductFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(?Portfolio $portfolio, bool $forceDelete = false, bool $fromWebhook = false): null|int|WooCommerceUser
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $portfolio->customerSalesChannel->user;

        if (!$portfolio) {
            return null;
        }

        if (!$fromWebhook && $portfolio->platform_product_id) {
            $wooCommerceUser->deleteWooCommerceProduct($portfolio->platform_product_id, $forceDelete);
        }

        return null;
    }
}
