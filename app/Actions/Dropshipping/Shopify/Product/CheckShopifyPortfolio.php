<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckShopifyPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {

        $oldHasValidProductId=$portfolio->has_valid_platform_product_id;

        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        $shopifyUser = $portfolio->customerSalesChannel->user;

        if (!$shopifyUser instanceof ShopifyUser) {
            return $portfolio;
        } elseif (! $shopifyUser->checkConnection()) {
            return $portfolio;
        }

        $hasValidProductId      = CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInShopify = false;
        $hasVariantAtLocation   = false;
        if ($hasValidProductId) {
            $productExistsInShopify = CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id);
            $hasVariantAtLocation   = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);
        }


        $numberMatches = 0;
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



        if($oldHasValidProductId  && (
            !$productExistsInShopify || !$hasVariantAtLocation
            )  ){
            // todo INI-339
            // Do not proceed until we fix $productExistsInShopify and $hasVariantAtLocation if is a network error or throttling

            return $portfolio;
        }


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

    public function getCommandSignature(): string
    {
        return 'shopify:check_portfolio {portfolio_id}';
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));
        $this->handle($portfolio);
    }


}
