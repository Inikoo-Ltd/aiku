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

    public function handle(Webpage $webpage, array $webBlock): array
    {
        if ($webpage->model_type == 'Collection') {

            $products=IrisProductsInWebpageResource::collection(GetIrisProductsInCollection::run(collection: $webpage->model));

            /* $productRoute = [
                'workshop' => [
                    'name'       => 'grp.json.collection.products.index',
                    'parameters' => ['collection' => $webpage->model_id],
                ],
                'iris'     => [
                    'route_products'              => [
                        'name'       => 'iris.json.collection.products.index',
                        'parameters' => ['collection' => $webpage->model_id],
                    ],
                    'route_out_of_stock_products' => [
                        'name'       => 'iris.json.collection.out_of_stock_products.index',
                        'parameters' => ['collection' => $webpage->model_id],
                    ]
                ],
            ]; */
        } else {
            $products=IrisProductsInWebpageResource::collection(GetIrisProductsInProductCategory::run(productCategory: $webpage->model, inStock: true));

           /*  $productRoute = [
                'workshop' => [
                    'name'       => 'grp.json.product_category.products.index',
                    'parameters' => ['productCategory' => $webpage->model_id],
                ],
                'iris'     => [
                    'route_products'              => [
                        'name'       => 'iris.json.product_category.products.index',
                        'parameters' => ['productCategory' => $webpage->model_id],
                    ],
                    'route_out_of_stock_products' => [
                        'name'       => 'iris.json.product_category.out_of_stock_products.index',
                        'parameters' => ['productCategory' => $webpage->model_id],
                    ]
                ],
            ]; */
        }


        $permissions = [];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['products']['data']['fieldValue'] ?? []);
       /*  data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute); */
        data_set($webBlock, 'web_block.layout.data.fieldValue.products', $products);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_type', $webpage->model_type);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_id', $webpage->model_id);


        return $webBlock;
    }

}
