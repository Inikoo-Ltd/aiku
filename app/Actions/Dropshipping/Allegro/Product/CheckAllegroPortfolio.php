<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckAllegroPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        /** @var AllegroUser $allegroUser */
        $allegroUser = $portfolio->customerSalesChannel->user;

        if (!$allegroUser instanceof AllegroUser) {
            return $portfolio;
        }

        $hasValidProductId      = CheckIfAllegroProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInAllegro = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $result = (bool) $portfolio->platform_product_id;
            $productExistsInAllegro = $result;
            $hasVariantAtLocation   = $result;
        }

        $numberMatches = 0;
        $matchesLabels = [];
        $matches       = [];

        if (!$hasValidProductId || !$productExistsInAllegro || !$hasVariantAtLocation) {
            $result = CheckIfProductExistInAllegro::run($allegroUser, $portfolio);

            $matches       = Arr::get($result, 'data');
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
            'exist_in_platform'                => $productExistsInAllegro,
            'platform_status'                  => $hasVariantAtLocation,
            'platform_possible_matches'        => $matchData,
            'number_platform_possible_matches' => $numberMatches
        ]);

        if ($hasVariantAtLocation) {
            $data = $portfolio->data;
            data_set($data, 'allegro_product', Arr::get($result, 'data'));

            $dataToUpdate = [
                'data' => $data
            ];

            UpdatePortfolio::run($portfolio, $dataToUpdate);
        }

        return $portfolio;
    }
}
