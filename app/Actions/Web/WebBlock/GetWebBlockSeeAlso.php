<?php
/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Http\Resources\Catalogue\ProductsWebpageResource;
use App\Http\Resources\Web\WebBlockCollectionResource;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockSeeAlso
{
    use AsObject;

    public function handle(array $webBlock): array
    {
        $permissions = ['hidden'];

        $products = Arr::get($webBlock, 'web_block.layout.data.fieldValue.settings.products_data.products', []);

        $ids = collect($products)->pluck('id')->all();
        $productsModel = Product::whereIn('id', $ids)->get();

        $productOverwrite = ProductsWebpageResource::collection($productsModel)->resolve();

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue.settings.products_data.products', $productOverwrite);

        return $webBlock;
    }

}
