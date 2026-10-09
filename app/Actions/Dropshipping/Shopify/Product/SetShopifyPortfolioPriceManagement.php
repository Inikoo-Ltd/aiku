<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 11:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\Concerns\AsAction;

class SetShopifyPortfolioPriceManagement
{
    use AsAction;

    public string $jobQueue = 'shopify-bulk';

    /**
     * Switching on sends our current price to the merchant's variant at once; switching off stops
     * the sends and leaves the last price in Shopify as it is.
     *
     * @return array{0: bool, 1: string}
     */
    public function handle(Portfolio $portfolio, bool $managedByUs): array
    {
        if (!$portfolio->isShopifyVariantAdopted()) {
            return [false, 'Only products linked to a variant you already had in Shopify can change who manages the price'];
        }

        $portfolio->markShopifyPriceManagedByUs($managedByUs);

        if (!$managedByUs) {
            return [true, ''];
        }

        return UpdateShopifyProductVariant::run($portfolio->refresh());
    }
}
