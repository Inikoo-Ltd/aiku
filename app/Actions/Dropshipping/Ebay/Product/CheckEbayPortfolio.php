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

        $ebayUser = $portfolio->customerSalesChannel->user;

        if (!$ebayUser instanceof EbayUser) {
            return $portfolio;
        }


        $hasValidProductId      = CheckIfEbayProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInEbay = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $result = CheckIfProductExistInEbay::run($ebayUser, $portfolio);
            $productExistsInEbay = ! blank($result);
            $hasVariantAtLocation   = $productExistsInEbay;
        }

        $matches = $hasVariantAtLocation ? [] : self::possibleMatches($ebayUser, $portfolio);

        $matchData = [
            'number_matches' => count($matches),
            'matches_labels' => Arr::pluck($matches, 'name'),
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

    /**
     * @return array<int, array{id: string, name: string, images: array<int, array{src: string}>}>
     */
    public static function possibleMatches(EbayUser $ebayUser, Portfolio $portfolio): array
    {
        if (blank($portfolio->sku)) {
            return [];
        }

        $offer = CheckIfProductExistInEbay::publishedOffer($ebayUser->getOffers(['sku' => $portfolio->sku]));

        if (!$offer) {
            return [];
        }

        return [self::matchFromOffer($offer, $ebayUser->getProduct(Arr::get($offer, 'sku')))];
    }

    /**
     * The retina match button posts raw_data[0].id as platform_product_id, which the eBay matcher looks up by SKU.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $inventoryItem
     * @return array{id: string, name: string, images: array<int, array{src: string}>}
     */
    public static function matchFromOffer(array $offer, array $inventoryItem): array
    {
        $sku = (string) Arr::get($offer, 'sku');

        return [
            'id'     => $sku,
            'name'   => (string) Arr::get($inventoryItem, 'product.title', $sku),
            'images' => array_map(fn (string $url) => ['src' => $url], Arr::get($inventoryItem, 'product.imageUrls', [])),
        ];
    }
}
