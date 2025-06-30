<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:22:15 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Actions\Catalogue\Product\Json\GetIrisProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProducts
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock, bool $isLoggedIn): array
    {
        $stockMode = $isLoggedIn ? 'in_stock' : 'all';

        if ($webpage->model_type == 'Collection') {
            $products = IrisProductsInWebpageResource::collection(GetIrisProductsInCollection::run(collection: $webpage->model, stockMode: $stockMode));
            if ($isLoggedIn) {
                $productsOutOfStock = IrisProductsInWebpageResource::collection(GetIrisProductsInCollection::run(collection: $webpage->model, stockMode: 'out_of_stock'));
            }
        } else {
            $products = IrisProductsInWebpageResource::collection(GetIrisProductsInProductCategory::run(productCategory: $webpage->model, stockMode: $stockMode));
            if ($isLoggedIn) {
                $productsOutOfStock = IrisProductsInWebpageResource::collection(GetIrisProductsInProductCategory::run(productCategory: $webpage->model, stockMode: 'out_of_stock'));
            }
        }


        $permissions = [];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['products']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products', $products);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_type', $webpage->model_type);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_id', $webpage->model_id);
        if ($isLoggedIn) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.products_out_of_stock', $productsOutOfStock);
        }

        return $webBlock;
    }

}
