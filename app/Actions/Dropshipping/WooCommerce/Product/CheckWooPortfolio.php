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

    /**
     * When the store cannot be asked (error page, timeout) the portfolio keeps whatever it had:
     * flipping a live listing to "missing" on a transient failure would drop it from stock sync
     * with nothing scheduled to put it back.
     */
    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        $wooUser = $portfolio->customerSalesChannel->user;

        if (!$wooUser instanceof WooCommerceUser) {
            return $portfolio;
        }


        $hasValidProductId      = CheckIfWooProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInWoo = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $reply  = $wooUser->getWooCommerceProduct($portfolio->platform_product_id);
            $result = CheckIfProductExistInWoo::onlyProducts([$reply]);

            if (blank($result) && !CheckIfProductExistInWoo::isMissingProductReply($reply)) {
                return $portfolio;
            }

            $productExistsInWoo = ! blank($result);
            $hasVariantAtLocation   = $productExistsInWoo;
        }

        $matches       = $hasVariantAtLocation ? [] : CheckIfProductExistInWoo::possibleMatches($wooUser, $portfolio);
        $numberMatches = count($matches);

        $matchData = [
            'number_matches' => $numberMatches,
            'matches_labels' => Arr::pluck($matches, 'name'),
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
