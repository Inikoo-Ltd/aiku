<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:22:15 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProducts
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {




        if ($webpage->model_type == 'Collection') {


            $productRoute = [
                'workshop' => [
                    'name' => 'grp.json.collection.products.index',
                    'parameters' => ['collection' => $webpage->model_id],
                ],
                'iris' => [
                    'route_products' => [
                        'name' => 'iris.json.product_category.products.index',
                        'parameters' => ['productCategory' => $webpage->model_id],
                    ],
                    'route_out_of_stock_products' => [
                        //todo
                       /*  'name' => 'iris.collection.out_of_stock_products.index', */
                        'name' => 'iris.json.product_category.products.index',
                        'parameters' => ['productCategory' => $webpage->model_id],
                    ]
                ],
            ];
        } else {
            $productRoute = [
                'workshop' => [
                    'name' => 'grp.json.product_category.products.index',
                    'parameters' => ['productCategory' => $webpage->model_id],
                ],
                'iris' => [
                    'route_products' => [
                        'name' => 'iris.json.product_category.products.index',
                        'parameters' => ['productCategory' => $webpage->model_id],
                    ],
                    'route_out_of_stock_products' => [
                          //todo
                       /*  ''name' => 'iris.product_category.out_of_stock_products.index',*/
                        'name' => 'iris.json.product_category.products.index',
                        'parameters' => ['productCategory' => $webpage->model_id],
                    ]
                ],
            ];
        }


        $permissions =  [];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['products']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);


        return $webBlock;
    }

}
