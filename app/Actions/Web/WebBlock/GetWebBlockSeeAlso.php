<?php

/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Actions\Catalogue\Product\Json\GetTopProductsInProductCategory;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Catalogue\ProductsWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockSeeAlso
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $products = Arr::get($webBlock, 'web_block.layout.data.fieldValue.settings.products_data.products', []);

        // Section: Other Family
        $dataOtherFamilyToWorkshop          = null;
        $idOtherFamily                  = Arr::get($webBlock, 'web_block.layout.data.fieldValue.settings.products_data.other_family.id', []);
        if ($idOtherFamily && is_numeric($idOtherFamily)) {
            $modelOtherFamily               = ProductCategory::find($idOtherFamily);
            if ($modelOtherFamily) {
                $dataOtherFamilyToWorkshop      = [
                    'id'    => $idOtherFamily,
                    'slug'  => $modelOtherFamily?->slug,
                    'name'  => $modelOtherFamily?->name,
                    'code'  => $modelOtherFamily?->code,
                    'title' => $modelOtherFamily?->title,
                    'option'   => ProductsWebpageResource::collection(
                        $modelOtherFamily->getProducts()->sortByDesc('id')->take(6)
                    )->resolve()
                ];
            }
        }

        $ids = collect($products)
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $productsModel = Product::whereIn('id', $ids)->get();

        $productOverwrite = collect(
            ProductsWebpageResource::collection($productsModel)->resolve()
        )->keyBy('id');

        $mergedProducts = collect($products)->map(function ($product) use ($productOverwrite) {
            return is_numeric($product['id'])
                ? ($productOverwrite[$product['id']] ?? $product)
                : $product;
        });

        $family = $webpage->model;
        $productsInCurrentFamily = null;
        if ($webpage->sub_type == WebpageSubTypeEnum::PRODUCT) {
            $family = $webpage->model->family;
            $productsInCurrentFamily = [
                'id'    => $family->id,
                'slug'  => $family->slug,
                'name' => $family->name,
                'option' => ProductsWebpageResource::collection($family->getProducts()->sortByDesc('id')->take(6))->resolve()
            ];

        }

        $topProducts = ProductsWebpageResource::collection(GetTopProductsInProductCategory::run($family))->resolve();

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.products_data.products',
            $mergedProducts->values()->all()
        );
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.products_data.top_sellers',
            $topProducts
        );
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.products_data.current_family',
            $productsInCurrentFamily
        );
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.products_data.other_family',
            $dataOtherFamilyToWorkshop
        );

        // dd($webBlock);
        return $webBlock;
    }
}
