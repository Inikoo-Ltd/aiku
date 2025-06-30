<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FamilyHydrateBestSellerProduct implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public function getJobUniqueId(ProductCategory $family): string
    {
        return $family->id;
    }

    public function handle(ProductCategory $family): void
    {
        if ($family->type !== ProductCategoryTypeEnum::FAMILY) {
            return;
        }


        // Get products associated with this family
        $products = Product::query()
            ->where('family_id', $family->id)
            ->where('products.is_main', true)
            ->whereNull('products.exclusive_for_customer_id')
            ->join('assets', 'products.asset_id', '=', 'assets.id')
            ->leftJoin('asset_stats', 'assets.id', '=', 'asset_stats.asset_id')
            ->select('products.*')
            ->with(['asset.salesIntervals'])
            ->get();
        // Filter products with sales and sort by sales in descending order
        $topProducts = $products->filter(function ($product) {
            return $product->asset && $product->asset->salesIntervals &&
                isset($product->asset->salesIntervals->sales_all) &&
                $product->asset->salesIntervals->sales_all > 0;
        })->sortByDesc(function ($product) {
            return $product->asset->salesIntervals->sales_all;
        });

        // Determine how many top products to mark based on total count
        $totalProducts = $products->count();
        $topCount = 0;

        if ($totalProducts >= 4) {
            $topCount = 1;
            if ($totalProducts >= 5) {
                $topCount = 2;
                if ($totalProducts >= 7) {
                    $topCount = 3;
                }
            }
            $topProducts = $topProducts->take($topCount);
        } else {
            // If less than 4 products, don't update any
            $topProducts = collect();
        }


        // Reset all products top_seller flag first
        foreach ($products as $product) {
            if ($product->top_seller > 0) {
                $product->update([
                    'top_seller' => null
                ]);
            }
        }

        // Update top products
        $index = 0;
        foreach ($topProducts as $product) {
            $product->update([
                'top_seller' => $index + 1,
            ]);
            $index++;
        }

    }

}
