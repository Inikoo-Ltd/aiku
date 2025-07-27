<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckShopifyPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        $shopifyUser = $portfolio->customerSalesChannel->user;

        $hasValidProductId      = CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInShopify = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $productExistsInShopify = CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id);
            $hasVariantAtLocation   = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);
        }


        $numberMatches = '';
        $matchesLabels = [];
        $matches       = [];

        if (!$hasValidProductId || !$productExistsInShopify || !$hasVariantAtLocation) {
            $result = FindShopifyProductVariant::run($portfolio->customerSalesChannel, trim($portfolio->sku.' '.$portfolio->barcode));

            $matches       = Arr::get($result, 'products', []);
            $numberMatches = count($matches);
            $matchesLabels = Arr::pluck($matches, 'title');
        }


        $matchData = [
            'number_matches' => $numberMatches,
            'matches_labels' => $matchesLabels,
            'raw_data'       => $matches

        ];


        $portfolio->update([
            'has_valid_platform_product_id'    => $hasValidProductId,
            'exist_in_platform'                => $productExistsInShopify,
            'platform_status'                  => $hasVariantAtLocation,
            'platform_possible_matches'        => $matchData,
            'number_platform_possible_matches' => $numberMatches

        ]);

        if ($hasVariantAtLocation) {
            SaveShopifyProductData::run($portfolio);
        }


        return $portfolio;
    }


}
