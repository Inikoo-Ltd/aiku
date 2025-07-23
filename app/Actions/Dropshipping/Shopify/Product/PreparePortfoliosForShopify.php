<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 20:04:47 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;


class PreparePortfoliosForShopify
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel, int $fixLevel = 1): array
    {
        $portfoliosSynchronisation = [];
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        foreach ($customerSalesChannel->portfolios as $portfolio) {
            /** @var Product $product */
            $product = $portfolio->item;

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
                $result = FindShopifyProductVariant::run($customerSalesChannel, trim($portfolio->sku.' '.$portfolio->barcode));

                $matches       = Arr::get($result, 'products', []);
                $numberMatches = count($matches);
                $matchesLabels = Arr::pluck($matches, 'title');
            }


            if ($fixLevel >= 1) {
                if ($hasValidProductId && !$hasVariantAtLocation && $numberMatches == 0) {
                    StoreShopifyProductVariant::run($portfolio);
                    $hasVariantAtLocation = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);
                }

                if (!$hasValidProductId || !$productExistsInShopify && $numberMatches == 0) {
                    StoreShopifyProduct::run($portfolio);
                    $hasVariantAtLocation = CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id);
                }
            }

            if ($fixLevel >= 1 && $hasValidProductId && $productExistsInShopify) {
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
            }


            $portfoliosSynchronisation[$portfolio->id] = [
                'product_code'              => $product->code,
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

    public function getCommandSignature(): string
    {
        return 'shopify:prepare_portfolios_for_shopify {customerSalesChannel} {--fix_level=0 : Fix level (1, 2, or 3)}';
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
            ['#', 'ID', 'Product Code', 'SKU', 'Barcode', 'Valid Product ID', 'Exists in Shopify', 'At Location', 'Matches', 'Match Labels'],
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
