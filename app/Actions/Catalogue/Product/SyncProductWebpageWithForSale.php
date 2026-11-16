<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Web\Webpage\CloseDiscontinuedWebpage;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The storefront answers 404 for a product that is not for sale (unless it leads a variant), so its
 * webpage must not stay live: it is closed with a 301 to the nearest live family, sub-department,
 * department or home page. A page is reopened only when the product turns for sale again, so a page
 * closed by hand stays closed while nothing changes.
 */
class SyncProductWebpageWithForSale
{
    use AsObject;

    public function handle(Product $product, bool $forSaleChanged): void
    {
        $webpage = $product->webpage;
        if (!$webpage) {
            return;
        }

        if (!$product->is_for_sale && !$product->is_variant_leader) {
            CloseDiscontinuedWebpage::run($webpage);

            return;
        }

        if ($forSaleChanged
            && $product->is_for_sale
            && $webpage->state == WebpageStateEnum::CLOSED
            && !in_array($product->state, [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])) {
            ReopenWebpage::run($webpage);
        }
    }
}
