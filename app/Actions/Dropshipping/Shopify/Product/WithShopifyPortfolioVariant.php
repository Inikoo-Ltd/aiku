<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 17:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;

trait WithShopifyPortfolioVariant
{
    /**
     * A portfolio linked to a variant the merchant already had shares its Shopify product with
     * sibling portfolios, so only its own variant speaks for it. Every other portfolio owns the
     * product and keeps reading its first variant.
     *
     * @param  array<int, array{node: array}>  $variantEdges
     */
    protected function portfolioVariantNode(Portfolio $portfolio, array $variantEdges): ?array
    {
        if (!$portfolio->isShopifyVariantAdopted()) {
            return Arr::get($variantEdges, '0.node');
        }

        foreach ($variantEdges as $variantEdge) {
            if (Arr::get($variantEdge, 'node.id') === $portfolio->platform_product_variant_id) {
                return Arr::get($variantEdge, 'node');
            }
        }

        return null;
    }
}
