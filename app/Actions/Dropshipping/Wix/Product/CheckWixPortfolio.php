<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckWixPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        /** @var WixUser $wixUser */
        $wixUser = $portfolio->customerSalesChannel->user;

        if (!$wixUser instanceof WixUser) {
            return $portfolio;
        }

        $hasValidProductId  = CheckIfWixProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInWix = false;
        $wixProduct         = null;

        if ($hasValidProductId) {
            $wixProduct         = Arr::get($wixUser->getProduct($portfolio->platform_product_id), 'product');
            $productExistsInWix = (bool) $wixProduct;
        }

        $matches       = [];
        $numberMatches = 0;
        $matchesLabels = [];

        if (!$productExistsInWix) {
            $matches       = CheckIfProductExistInWix::run($wixUser, $portfolio);
            $numberMatches = count($matches);
            $matchesLabels = Arr::pluck($matches, 'name');
        }

        $portfolio->update([
            'has_valid_platform_product_id'    => $hasValidProductId,
            'exist_in_platform'                => $productExistsInWix,
            'platform_status'                  => $productExistsInWix,
            'platform_possible_matches'        => [
                'number_matches' => $numberMatches,
                'matches_labels' => $matchesLabels,
                'raw_data'       => $matches
            ],
            'number_platform_possible_matches' => $numberMatches
        ]);

        if ($productExistsInWix) {
            $data = $portfolio->data;
            data_set($data, 'wix_product', $wixProduct);

            UpdatePortfolio::run($portfolio, ['data' => $data]);
        }

        return $portfolio;
    }
}
