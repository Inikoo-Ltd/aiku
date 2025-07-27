<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 20:04:47 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FixShopifyPortfolios
{
    use AsAction;

    public function handle(Shop|CustomerSalesChannel $parent, int $fixLevel = null): array
    {
        $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        $query = DB::table('portfolios')->select('id')->where('platform_id', $shopifyPlatform->id);

        if ($parent instanceof Shop) {
            $query->where('shop_id', $parent->id);
        } else {
            $query->where('customer_sales_channel_id', $parent->id);
        }

        $portfoliosSynchronisation = [];
        foreach ($query->orderBy('status')->get() as $portfolioData) {
            $portfolio = Portfolio::find($portfolioData->id);
            if (!$portfolio) {
                continue;
            }

            if (!$portfolio->customerSalesChannel) {
                continue;
            }

            if (!$portfolio->platform_status) {
                continue;
            }

            /** @var ShopifyUser $shopifyUser */
            $shopifyUser = $portfolio->customerSalesChannel->user;
            if (!$shopifyUser) {
                continue;
            }


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
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel1($portfolio, $shopifyUser, $productExistsInShopify);
                } elseif ($fixLevel == 2) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel2($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);
                } elseif ($fixLevel == 3) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel3($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);
                } elseif ($fixLevel == 4) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel4($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);
                } elseif ($fixLevel == 5) {
                    list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel5($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);
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

    public function fixLevel1(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $productExistsInShopify): array
    {
        if ($productExistsInShopify) {
            StoreShopifyProductVariant::run($portfolio);
        }

        return [
            CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id),
            CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id),
            CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id)
        ];
    }

    public function fixLevel2(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $productExistsInShopify, int $numberMatches, array $matches): array
    {
        list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel1($portfolio, $shopifyUser, $productExistsInShopify);

        if ($hasVariantAtLocation) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }

        if (!$productExistsInShopify && $numberMatches == 1) {
            $firstMatch       = Arr::first($matches);
            $shopifyProductId = Arr::get($firstMatch, 'id');

            $portfolio->update([
                'platform_product_id' => $shopifyProductId,
            ]);

            $portfolio->refresh();
            StoreShopifyProductVariant::run($portfolio);
        }


        return [
            CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id),
            CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id),
            CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id)
        ];
    }

    public function fixLevel3(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $productExistsInShopify, int $numberMatches, array $matches): array
    {
        list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel2($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);

        if ($hasVariantAtLocation) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }


        if ($numberMatches == 0) {
            if (!$hasValidProductId) {
                StoreShopifyProduct::run($portfolio);
            } elseif ($productExistsInShopify) {
                StoreShopifyProductVariant::run($portfolio);
            } else {
                StoreShopifyProduct::run($portfolio);
            }
        }

        return [
            CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id),
            CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id),
            CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id)
        ];
    }

    public function fixLevel4(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $productExistsInShopify, int $numberMatches, array $matches): array
    {
        list($hasValidProductId, $productExistsInShopify, $hasVariantAtLocation) = $this->fixLevel3($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);

        if ($hasVariantAtLocation) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }

        if (empty($matches)) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }


        $firstMatch       = Arr::first($matches);
        $shopifyProductId = Arr::get($firstMatch, 'id');

        $portfolio->update([
            'platform_product_id' => $shopifyProductId,
        ]);

        $portfolio->refresh();
        StoreShopifyProductVariant::run($portfolio);

        return [
            CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id),
            CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id),
            CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id)
        ];
    }

    public function fixLevel5(Portfolio $portfolio, ShopifyUser $shopifyUser, bool $productExistsInShopify, int $numberMatches, array $matches): array
    {
        list(
            $hasValidProductId, $productExistsInShopify, $hasVariantAtLocation
            ) =
            $this->fixLevel4($portfolio, $shopifyUser, $productExistsInShopify, $numberMatches, $matches);

        if ($hasVariantAtLocation) {
            return [$hasValidProductId, $productExistsInShopify, $hasVariantAtLocation];
        }


        StoreShopifyProduct::run($portfolio);


        return [
            CheckIfShopifyProductIDIsValid::run($portfolio->platform_product_id),
            CheckIfProductExistsInShopify::run($shopifyUser, $portfolio->platform_product_id),
            CheckIfProductHasVariantAtLocation::run($shopifyUser, $portfolio->platform_product_id)
        ];
    }


    public function getCommandSignature(): string
    {
        return 'shopify:fix_portfolios  {parent_type} {parent_slug} {--f|fix_level=0 : Fix level (1, 2, or 3)}';
    }

    public function asCommand(Command $command): void
    {
        $parentType = $command->argument('parent_type');
        $parentSlug = $command->argument('parent_slug');

        $parent = match (strtolower($parentType)) {
            'shp' => Shop::where('slug', $parentSlug)->firstOrFail(),
            'csc' => CustomerSalesChannel::where('slug', $parentSlug)->firstOrFail(),
            default => throw new \InvalidArgumentException("Invalid parent type: $parentType"),
        };


        $fixLevel = (int)$command->option('fix_level');

        // Validate fix_level
        if ($fixLevel < 0 || $fixLevel > 5) {
            $command->error("Invalid fix level: $fixLevel. Fix level must be 1, 2, 3, 4 or 5.");

            return;
        }

        $portfoliosSynchronisation = $this->handle($parent, $fixLevel);


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
