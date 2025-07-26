<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 20:04:47 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class FixShopifyPortfolios
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel, int $fixLevel = 1): array
    {
        $portfoliosSynchronisation = [];
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        foreach ($customerSalesChannel->portfolios()->orderBy('status')->get() as $portfolio) {
            $portfolio = CheckShopifyPortfolio::run($portfolio);

            /** @var Product $product */
            $product = $portfolio->item;


            $hasValidProductId      = $portfolio->has_valid_platform_product_id;
            $productExistsInShopify = $portfolio->exist_in_platform;
            $hasVariantAtLocation   = $portfolio->platform_status;


            $matchesData = $portfolio->platform_possible_matches;

            $numberMatches = Arr::get($matchesData, 'number_matches', 0);
            $matchesLabels = Arr::get($matchesData, 'matches_labels', []);
            $matches       = Arr::get($matchesData, 'raw_data', []);


            if (!$hasVariantAtLocation && $portfolio->status) {
                if ($fixLevel == 1) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel1($portfolio, $shopifyUser, $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation, $numberMatches);
                } elseif ($fixLevel == 2) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel2($portfolio, $shopifyUser, $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation, $numberMatches, $matches);
                } elseif ($fixLevel == 3) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel3($portfolio, $shopifyUser, $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation, $numberMatches, $matches);
                }
            }


            $portfoliosSynchronisation[$portfolio->id] = [
                'product_code'              => $product->code,
                'status'                    => $portfolio->status,
                'sku'                       => $portfolio->sku,
                'barcode'                   => $portfolio->barcode,
                'has_platform_product_id'   => $hasValidProductId,
                'product_exists_in_shopify' => $productExistsInShopify,
                'has_variant_at_location'   => $hasVariantAtLocation,
                'fix_level'                 => $fixLevel,
                'number_matches'            => $numberMatches,
                'matches_labels'            => $matchesLabels,
            ];
        }

        return $portfoliosSynchronisation;
    }


    public function fixLevel1(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $hasValidProductId, bool $productExistsInShopify, bool $hasVariantAtLocation, int $numberMatches): array
    {
        if ($numberMatches == 0) {
            if (!$hasValidProductId) {
                StoreShopifyProduct::run($portfolio);
            } elseif ($productExistsInShopify) {
                StoreShopifyProductVariant::run($portfolio);
            } else {
                StoreShopifyProduct::run($portfolio);
            }
        }

        $hasValidProductId      = CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInShopify = CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id);
        $hasVariantAtLocation   = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);

        return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
    }

    public function fixLevel2(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $hasValidProductId, bool $productExistsInShopify, bool $hasVariantAtLocation, int $numberMatches, array $matches): array
    {
        list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel1($portfolio, $shopifyUser, $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation, $numberMatches);

        if ($hasVariantAtLocation) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }

        if (count($matches) == 0) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }


        $firstMatch       = Arr::first($matches);
        $shopifyProductId = Arr::get($firstMatch, 'id');

        $portfolio->update([
            'platform_product_id' => $shopifyProductId,
        ]);

        $portfolio->refresh();
        StoreShopifyProductVariant::run($portfolio);
        $hasValidProductId      = CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInShopify = CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id);
        $hasVariantAtLocation   = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);


        return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
    }

    public function fixLevel3(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $hasValidProductId, bool $productExistsInShopify, bool $hasVariantAtLocation, int $numberMatches, array $matches): array
    {
        list(
            $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation
            ) =
            $this->fixLevel2($portfolio, $shopifyUser, $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation, $numberMatches, $matches);

        if ($hasVariantAtLocation) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }


        StoreShopifyProduct::run($portfolio);


        $hasValidProductId      = CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInShopify = CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id);
        $hasVariantAtLocation   = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);

        return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
    }


    public function getCommandSignature(): string
    {
        return 'shopify:fix_portfolios {customerSalesChannel} {--f|fix_level=0 : Fix level (1, 2, or 3)}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $fixLevel             = (int)$command->option('fix_level');

        // Validate fix_level
        if ($fixLevel < 0 || $fixLevel > 3) {
            $command->error("Invalid fix level: $fixLevel. Fix level must be 1, 2, or 3.");

            return;
        }

        $portfoliosSynchronisation = $this->handle($customerSalesChannel, $fixLevel);

        if (empty($portfoliosSynchronisation)) {
            $command->info("No portfolios found for synchronization.");

            return;
        }

        $tableData = [];
        $counter   = 1;

        foreach ($portfoliosSynchronisation as $portfolioId => $portfolio) {
            $tableData[] = [
                'counter'        => $counter,
                'id'             => $portfolioId,
                'status'         => $portfolio['status'] ? 'Open' : 'Closed',
                'product_code'   => $portfolio['product_code'] ?? 'N/A',
                'sku'            => $portfolio['sku'] ?? 'N/A',
                'barcode'        => $portfolio['barcode'] ?? 'N/A',
                'valid_id'       => $portfolio['has_platform_product_id'] ? 'Yes' : 'No',
                'exists'         => $portfolio['product_exists_in_shopify'] ? 'Yes' : 'No',
                'at_location'    => $portfolio['has_variant_at_location'] ? 'Yes' : 'No',
                'number_matches' => $portfolio['number_matches'] ?? 0,
                'matches_labels' => implode(', ', $portfolio['matches_labels'] ?? [])
            ];
            $counter++;
        }

        // Output results in table format
        $this->table(
            ['#', 'Status', 'ID', 'Product Code', 'SKU', 'Barcode', 'Valid Product ID', 'Exists in Shopify', 'At Location', 'Matches', 'Match Labels'],
            $tableData,
            $command
        );

        // Summary
        $totalPortfolios    = count($portfoliosSynchronisation);
        $validProductIds    = count(array_filter($portfoliosSynchronisation, function ($portfolio) {
            return $portfolio['has_platform_product_id'] ?? false;
        }));
        $existsInShopify    = count(array_filter($portfoliosSynchronisation, function ($portfolio) {
            return $portfolio['product_exists_in_shopify'] ?? false;
        }));
        $variantsAtLocation = count(array_filter($portfoliosSynchronisation, function ($portfolio) {
            return $portfolio['has_variant_at_location'] ?? false;
        }));


        $command->info("\nResults:");
        $command->info("- $validProductIds out of $totalPortfolios portfolios have valid Shopify product IDs");
        $command->info("- $existsInShopify out of $totalPortfolios portfolios exist in Shopify");
        $command->info("- $variantsAtLocation out of $totalPortfolios portfolios have variants at the specified location");
    }

    /**
     * Display a table in the console.
     */
    protected function table(array $headers, array $rows, Command $command): void
    {
        $command->table($headers, $rows);
    }

}
