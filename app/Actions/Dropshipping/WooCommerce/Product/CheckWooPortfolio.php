<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckWooPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        $WooUser = $portfolio->customerSalesChannel->user;

        if (!$WooUser instanceof WooCommerceUser) {
            return $portfolio;
        }


        $hasValidProductId      = CheckIfWooProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInWoo = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $productExistsInWoo = CheckIfProductExistInWoo::run($WooUser, $portfolio);
            $hasVariantAtLocation   = $productExistsInWoo;
        }

        $numberMatches = 0;
        $matchesLabels = [];
        $matches       = [];

        if (!$hasValidProductId || !$productExistsInWoo || !$hasVariantAtLocation) {
            $result = CheckIfProductExistInWoo::run($WooUser, $portfolio);

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
            'exist_in_platform'                => $productExistsInWoo,
            'platform_status'                  => $hasVariantAtLocation,
            'platform_possible_matches'        => $matchData,
            'number_platform_possible_matches' => $numberMatches

        ]);

        if ($hasVariantAtLocation) {
            SaveWooProductData::run($portfolio);
        }


        return $portfolio;
    }


}
