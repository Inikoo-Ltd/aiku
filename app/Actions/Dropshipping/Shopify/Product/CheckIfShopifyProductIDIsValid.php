<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:02:25 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use Lorisleiva\Actions\Concerns\AsAction;

class CheckIfShopifyProductIDIsValid
{
    use AsAction;

    /**
     * Check if a platform_product_id has a valid Shopify format
     *
     * @param  string|null  $platformProductId  The platform_product_id to validate
     *
     * @return bool True if the platform_product_id has a valid format, false otherwise
     */
    public function handle(?string $platformProductId): bool
    {
        if (!$platformProductId) {
            return false;
        }

        // Valid format: gid://shopify/Product/{numeric_id}
        return (bool)preg_match('/^gid:\/\/shopify\/Product\/\d+$/', $platformProductId);
    }
}