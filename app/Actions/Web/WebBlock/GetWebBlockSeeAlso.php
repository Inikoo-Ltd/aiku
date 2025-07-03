<?php
/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Http\Resources\Catalogue\ProductsWebpageResource;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockSeeAlso
{
    use AsObject;

    public function handle(array $webBlock): array
    {
        $products = Arr::get($webBlock, 'web_block.layout.data.fieldValue.settings.products_data.products', []);

        $ids = collect($products)
            ->pluck('id')
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        $productsModel = Product::whereIn('id', $ids)->get();

        // ✅ FIXED: Resolve first, then wrap in collect() to use keyBy()
        $productOverwrite = collect(
            ProductsWebpageResource::collection($productsModel)->resolve()
        )->keyBy('id');

        // Merge: only overwrite if id is numeric and found
        $mergedProducts = collect($products)->map(function ($product) use ($productOverwrite) {
            return is_numeric($product['id'])
                ? ($productOverwrite[$product['id']] ?? $product)
                : $product;
        });

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.products_data.products',
            $mergedProducts->values()->all()
        );

        return $webBlock;
    }
}
