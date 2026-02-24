<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckTiktokPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $portfolio->customerSalesChannel->user;

        if (!$tiktokUser instanceof TiktokUser) {
            return $portfolio;
        }

        $hasValidProductId      = CheckIfTiktokProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInTiktok = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $result = CheckIfProductExistInTiktok::run($tiktokUser, $portfolio);
            $productExistsInTiktok = ! blank(Arr::get($result, 'data'));
            $hasVariantAtLocation   = Arr::get($result, 'data.status') === 'ACTIVATE';
        }

        $numberMatches = 0;
        $matchesLabels = [];
        $matches       = [];

        if (!$hasValidProductId || !$productExistsInTiktok || !$hasVariantAtLocation) {
            $result = CheckIfProductExistInTiktok::run($tiktokUser, $portfolio);

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
            'exist_in_platform'                => $productExistsInTiktok,
            'platform_status'                  => $hasVariantAtLocation,
            'platform_possible_matches'        => $matchData,
            'number_platform_possible_matches' => $numberMatches
        ]);

        if ($hasVariantAtLocation) {
            $data = $portfolio->data;
            data_set($data, 'tiktok_product', Arr::get($result, 'data'));

            $dataToUpdate = [
                'data' => $data
            ];

            UpdatePortfolio::run($portfolio, $dataToUpdate);
        }

        return $portfolio;
    }
}
