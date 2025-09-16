<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEbayPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        $wooUser = $portfolio->customerSalesChannel->user;

        if (!$wooUser instanceof EbayUser) {
            return $portfolio;
        }


        $hasValidProductId      = CheckIfEbayProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInEbay = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $result = CheckIfProductExistInEbay::run($wooUser, $portfolio);
            $productExistsInEbay = ! blank($result);
            $hasVariantAtLocation   = $productExistsInEbay;
        }

        $numberMatches = 0;
        $matchesLabels = [];
        $matches       = [];

        if (!$hasValidProductId || !$productExistsInEbay || !$hasVariantAtLocation) {
            $result = CheckIfProductExistInEbay::run($wooUser, $portfolio);

            $matches       = $result;
            $numberMatches = count($matches);
            $matchesLabels = Arr::pluck($matches, 'name');
        }

        $matchData = [
            'number_matches' => $numberMatches,
            'matches_labels' => $matchesLabels,
            'raw_data'       => $matches

        ];

        $portfolio->update([
            'has_valid_platform_product_id'    => $hasValidProductId,
            'exist_in_platform'                => $productExistsInEbay,
            'platform_status'                  => $hasVariantAtLocation,
            'platform_possible_matches'        => $matchData,
            'number_platform_possible_matches' => $numberMatches

        ]);

        if ($hasVariantAtLocation) {
            SaveEbayProductData::run($portfolio);
        }


        return $portfolio;
    }


}
