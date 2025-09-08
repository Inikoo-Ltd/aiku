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

    /**
     * Handle building "See Also" web block data.
     *
     * @param Webpage $webpage
     * @param array<string,mixed> $webBlock
     * @return array<string,mixed>
     */
    public function handle(Webpage $webpage, array $webBlock): array
    {
        $settingsPath = 'web_block.layout.data.fieldValue.settings.products_data';

        // Products selected manually
        $products = Arr::get($webBlock, "{$settingsPath}.products", []);
        
        // Ensure type exists but don’t overwrite if already set
        $luigiTrackerId = data_get($webpage->website, 'settings.luigisbox.tracker_id');
        if (!Arr::has($webBlock, "{$settingsPath}.type")) {
            if ($webpage->sub_type == WebpageSubTypeEnum::PRODUCT) {
                data_set($webBlock, "{$settingsPath}.type", "current-family");
            } else if ($luigiTrackerId) {
                data_set($webBlock, "{$settingsPath}.type", "luigi-trends");
            }
        }


        // Section: Other Family
        $dataOtherFamilyToWorkshop = null;
        $idOtherFamily = (int) Arr::get($webBlock, "{$settingsPath}.other_family.id");
        if ($idOtherFamily > 0 && ($modelOtherFamily = ProductCategory::find($idOtherFamily))) {
            $dataOtherFamilyToWorkshop = [
                'id'    => $idOtherFamily,
                'slug'  => $modelOtherFamily->slug,
                'name'  => $modelOtherFamily->name,
                'code'  => $modelOtherFamily->code,
                'title' => $modelOtherFamily->title,
                'option' => ProductsWebpageResource::collection(
                    $modelOtherFamily->getProducts()
                        // ->where('stock', '>', 0)
                        ->sortByDesc('id')
                        ->take(6)
                )->resolve(),
            ];
        }

        // Merge manually-selected products with database values
        $ids = collect($products)
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $productsModel = Product::with(['images', 'category']) // eager-load to prevent N+1
            ->whereIn('id', $ids)
            ->get();

        $productOverwrite = collect(
            ProductsWebpageResource::collection($productsModel)->resolve()
        )->keyBy('id');

        $mergedProducts = collect($products)->map(function ($product) use ($productOverwrite) {
            return is_numeric($product['id'])
                ? ($productOverwrite[$product['id']] ?? $product)
                : $product;
        });

        // Section: Current Family (if product page)
        $family = $webpage->model;
        $productsInCurrentFamily = null;

        if ($webpage->sub_type == WebpageSubTypeEnum::PRODUCT) {
            $family = $webpage->model->family;
            $productsInCurrentFamily = [
                'id'    => $family->id,
                'slug'  => $family->slug,
                'name'  => $family->name,
                'option' => ProductsWebpageResource::collection(
                    $family->getProducts()->sortByDesc('id')->take(6)
                )->resolve(),
            ];
        }

        // Section: Top Products in family
        $topProducts = ProductsWebpageResource::collection(
            GetTopProductsInProductCategory::run($family)
        )->resolve();

        // Set final data back into webBlock
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

        return $webBlock;
    }
}
