<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:22:15 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Http\Resources\Web\WebBlockProductsResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProducts
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {


        if ($webpage->sub_type == 'department') {
            $field = 'department_id';
        } elseif ($webpage->sub_type == 'sub_department') {
            $field = 'aub_department_id';
        } else {
            $field = 'family_id';
        }

        $families = DB::table('products')
            ->leftJoin('webpages', function ($join) {
                $join->on('products.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Product');
            })
            ->select(['products.code', 'name', 'image_id', 'url', 'title'])
            ->where($field, $webpage->model_id)
            ->get();


        if($webpage->model_type=='collection') {


            $productRoute = [
                'workshop' => [
                    'name' => 'grp.json.collection.products.index',
                    'parameters' => ['collection'=>$webpage->model_id],
                ],
                'iris' => [
                    'name' => 'iris.json.collection.products.index',
                    'parameters' => ['collection'=>$webpage->model_id],
                ],
            ];
        }else{
            $productRoute = [
                'workshop' => [
                    'name' => 'grp.json.product_category.products.index',
                    'parameters' => ['productCategory'=>$webpage->model_id],
                ],
                'iris' => [
                    'name' => 'iris.json.product_category.products.index',
                    'parameters' => ['productCategory'=>$webpage->model_id],
                ],
            ];
        }



        $permissions =  [];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['products']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_route', $productRoute);

        if (!$families->isEmpty()) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.products', WebBlockProductsResource::collection($families)->toArray(request()));
        } else {
            data_set($webBlock, 'web_block.layout.data.fieldValue.products', []);
        }

        return $webBlock;
    }

}
